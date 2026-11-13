<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Walikelas;
use App\Survei\Periode;
use App\Survei\Pertanyaan;
use App\Survei\Respons;
use App\Survei\Target;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SurveiPembelajaranController extends Controller
{
    private function admin(): void { abort_unless(auth()->user()->isAdmin(), 403); }
    private function siswa() { $s = auth()->user()->siswa; abort_unless($s && $s->id_kelas, 403); return $s; }
    private function targetSiswa(Target $target): void {
        abort_unless($target->kelas_id === $this->siswa()->id_kelas, 403);
        abort_unless($target->periode && $target->periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');
    }
    public function index() {
        $user = auth()->user();
        if ($user->isAdmin()) return view('survei.admin', ['periodes'=>Periode::latest()->get(), 'kelas'=>Kelas::orderBy('tingkat')->orderBy('kelas')->get()]);
        if ($user->siswa) {
            $siswa = $this->siswa();
            $targets = Target::with('periode')->where('kelas_id', $siswa->id_kelas)->whereHas('periode', fn ($q) => $q->where('ditutup', false)->where('mulai','<=',now())->where('selesai','>=',now()))->orderBy('guru_nama')->get();
            $done = Respons::where('siswa_id',$siswa->uuid)->whereIn('target_id',$targets->modelKeys())->pluck('target_id')->all();
            return view('survei.siswa', compact('targets','done'));
        }
        abort_unless($user->guru, 403);
        $targets = Target::with('periode')->where('guru_id',$user->guru->uuid)->whereHas('periode', fn ($q) => $q->where(fn ($q) => $q->where('ditutup',true)->orWhere('selesai','<',now())))->orderBy('guru_nama')->get();
        return view('survei.guru', compact('targets'));
    }
    public function store(Request $request) {
        $this->admin();
        $v = $request->validate(['judul'=>'required|string|max:150','mulai'=>'required|date','selesai'=>'required|date|after:mulai','kelas'=>'required|array|min:1','kelas.*'=>['required','uuid','distinct',Rule::exists('kelas','uuid')]]);
        $periode = DB::transaction(function () use ($v) {
            $periode = Periode::create(['judul'=>$v['judul'],'mulai'=>$v['mulai'],'selesai'=>$v['selesai'],'dibuat_oleh'=>auth()->id(),'pertanyaan'=>['mapel'=>Pertanyaan::untuk('mapel'),'wali'=>Pertanyaan::untuk('wali'),'komentar_mapel'=>Pertanyaan::terbuka('mapel'),'komentar_wali'=>Pertanyaan::terbuka('wali')]]);
            $seen = [];
            foreach (Ngajar::with(['kelas','guru','pelajaran'])->whereIn('id_kelas',$v['kelas'])->get() as $n) {
                if (!$n->kelas || !$n->guru || !$n->pelajaran) continue;
                $key = $n->id_kelas.':'.$n->id_guru.':'.$n->id_pelajaran;
                if (isset($seen[$key])) continue; $seen[$key]=true;
                $periode->targets()->create(['kelas_id'=>$n->id_kelas,'guru_id'=>$n->id_guru,'pelajaran_id'=>$n->id_pelajaran,'jenis'=>'mapel','kelas_nama'=>$n->kelas->nama_lengkap,'guru_nama'=>$n->guru->nama,'pelajaran_nama'=>$n->pelajaran->nama]);
            }
            foreach (Walikelas::with(['kelas','guru'])->whereIn('id_kelas',$v['kelas'])->get() as $w) {
                if (!$w->kelas || !$w->guru) continue;
                $key = 'wali:'.$w->id_kelas.':'.$w->id_guru;
                if (isset($seen[$key])) continue; $seen[$key]=true;
                $periode->targets()->create(['kelas_id'=>$w->id_kelas,'guru_id'=>$w->id_guru,'jenis'=>'wali','kelas_nama'=>$w->kelas->nama_lengkap,'guru_nama'=>$w->guru->nama]);
            }
            abort_if($periode->targets()->count() === 0, 422, 'Tidak ada guru pengampu atau wali kelas pada kelas yang dipilih.');
            \App\Support\Audit::log('survei_periode_dibuat', $periode);
            return $periode;
        });
        return redirect()->route('survei.periode', $periode)->with('success','Survei dibuat. Periksa daftar sasaran dan bagikan QR saat survei dibuka.');
    }
    public function periode(Periode $periode) { $this->admin(); $targets = $periode->targets()->orderBy('kelas_nama')->orderBy('guru_nama')->withCount('respons')->get(); return view('survei.periode', compact('periode','targets')); }
    public function tutup(Periode $periode) { $this->admin(); DB::transaction(function () use ($periode) { $p = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail(); if (! $p->ditutup) { $p->update(['ditutup'=>true]); \App\Support\Audit::log('survei_periode_ditutup', $p); } }); return back()->with('success','Survei telah ditutup dan tidak dapat dibuka kembali.'); }
    public function masuk(Periode $periode) {
        $siswa = $this->siswa();
        abort_unless($periode->terbuka() && $periode->targets()->where('kelas_id',$siswa->id_kelas)->exists(), 403, 'Survei tidak tersedia untuk kelasmu.');
        return redirect()->route('survei.index');
    }
    public function isi(Target $target) {
        $this->targetSiswa($target);
        if (Respons::where('target_id',$target->uuid)->where('siswa_id',$this->siswa()->uuid)->exists()) return redirect()->route('survei.index')->with('success','Survei ini sudah dikirim.');
        $pertanyaan=$target->periode->pertanyaan[$target->jenis]; $komentar=$target->periode->pertanyaan['komentar_'.$target->jenis];
        return view('survei.isi', compact('target','pertanyaan','komentar'));
    }
    public function kirim(Request $request, Target $target) {
        $this->targetSiswa($target); $siswa=$this->siswa();
        $pertanyaan=$target->periode->pertanyaan[$target->jenis]; $komentar=$target->periode->pertanyaan['komentar_'.$target->jenis];
        $rules=['jawaban'=>'required|array|size:'.count($pertanyaan),'komentar'=>'nullable|array'];
        foreach ($pertanyaan as $i => $_) $rules['jawaban.'.$i] = ['required',Rule::in(['0','1','2','3','4','5'])];
        foreach ($komentar as $i => $_) $rules['komentar.'.$i] = 'nullable|string|max:1000';
        $v=$request->validate($rules);
        $v['komentar'] = array_map(fn ($i) => $v['komentar'][$i] ?? null, array_keys($komentar));
        DB::transaction(function () use ($target,$siswa,$v) {
            $p=Periode::whereKey($target->periode_id)->lockForUpdate()->firstOrFail();
            abort_unless($p->terbuka(),403,'Survei telah ditutup.');
            if (Respons::where('target_id',$target->uuid)->where('siswa_id',$siswa->uuid)->exists()) return;
            Respons::create(['target_id'=>$target->uuid,'siswa_id'=>$siswa->uuid,'jawaban'=>$v['jawaban'],'komentar'=>$v['komentar'] ?? []]);
        });
        return redirect()->route('survei.index')->with('success','Jawaban tersimpan. Terima kasih atas masukanmu.');
    }
    public function export(Periode $periode) {
        $this->admin();
        abort_unless($periode->ditutup || now()->greaterThan($periode->selesai), 403, 'Ekspor tersedia setelah survei ditutup.');
        \App\Support\Audit::log('survei_rekap_diekspor', $periode);
        return response()->streamDownload(function () use ($periode) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kelas','Guru','Mata pelajaran','Pertanyaan','Respons total','Jawaban dinilai','Tidak dapat menilai','Rata-rata 1–5']);
            $safe = static fn ($v) => preg_match('/^[\s]*[=+@\-\t\r]/u', (string) $v) ? "'".$v : $v;
            foreach ($periode->targets()->with('respons')->get() as $target) {
                $responses = $target->respons; $count = $responses->count();
                if ($count < 5) continue;
                foreach ($periode->pertanyaan[$target->jenis] as $i => $text) {
                    $values = $responses->map(fn ($r) => (int) ($r->jawaban[$i] ?? 0));
                    $valid = $values->filter(fn ($v) => $v >= 1 && $v <= 5);
                    if ($valid->count() < 5) continue;
                    fputcsv($out, array_map($safe, [$target->kelas_nama,$target->guru_nama,$target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama,$text,$count,$valid->count(),$count-$valid->count(),round($valid->avg(),2)]));
                }
            }
            fclose($out);
        }, 'rekap-survei.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function hasil(Target $target) {
        $user=auth()->user(); $admin=$user->isAdmin();
        abort_unless($admin || ($user->guru && $target->guru_id === $user->guru->uuid),403);
        abort_unless($target->periode->ditutup || now()->greaterThan($target->periode->selesai),403,'Hasil tersedia setelah survei ditutup.');
        \App\Support\Audit::log('survei_hasil_dibuka', $target);
        $responses=$target->respons()->get(); $jumlah=$responses->count(); $pertanyaan=$target->periode->pertanyaan[$target->jenis];
        $hasil=[];
        if ($jumlah >= 5) foreach ($pertanyaan as $i=>$text) {
            $values=$responses->map(fn ($r) => (int) ($r->jawaban[$i] ?? 0));
            $valid=$values->filter(fn ($v) => $v >= 1 && $v <= 5);
            $hasil[]=['text'=>$text,'rata'=>$valid->count() ? round($valid->avg(),2) : null,'n'=>$valid->count(),'tidak'=>$values->count()-$valid->count()];
        }
        // Komentar hanya petugas admin; tidak dibagikan otomatis ke guru.
        $komentar=$admin && $jumlah >= 5 ? $responses->flatMap(fn ($r) => array_filter($r->komentar ?? []))->values() : collect();
        return view('survei.hasil',compact('target','jumlah','hasil','komentar','admin'));
    }
}
