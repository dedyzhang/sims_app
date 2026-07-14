@extends('layouts.app')
@section('title', 'Tim — '.$quiz->title)

@section('content')
<div class="space-y-5 max-w-2xl mx-auto" x-data="teamBuilder(@js(
    $teams->map(fn($t) => [
        'name' => $t->name,
        'member_ids' => $t->members->pluck('user_id')->values(),
    ])->values()->all() ?: [['name' => 'Tim A', 'member_ids' => []], ['name' => 'Tim B', 'member_ids' => []]]
), @js($members->map(fn($m) => ['id' => $m->user_id, 'name' => $m->user?->displayName() ?? 'Siswa'])->values()))">
    <div>
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="text-sm text-slate-500 inline-flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
        </a>
        <h1 class="text-xl font-black">Mode Tim</h1>
        <p class="text-sm text-slate-500">Bagi siswa ke kelompok untuk podium agregat.</p>
    </div>

    <form method="POST" action="{{ route('classroom.arena.teams.save', [$classroom, $quiz]) }}" class="space-y-4">
        @csrf
        <template x-for="(team, ti) in teams" :key="ti">
            <div class="card p-4 space-y-3">
                <div class="flex gap-2">
                    <input type="text" x-model="team.name" :name="'teams['+ti+'][name]'" required
                           class="flex-1 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-3 text-sm min-h-[44px]">
                    <button type="button" class="text-rose-500 text-sm px-2" @click="teams.splice(ti,1)" x-show="teams.length>1">Hapus</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                    <template x-for="m in students" :key="m.id">
                        <label class="flex items-center gap-2 text-sm min-h-[40px]">
                            <input type="checkbox" :value="m.id" :name="'teams['+ti+'][member_ids][]'"
                                   :checked="team.member_ids.includes(m.id)"
                                   @change="toggle(ti, m.id, $event.target.checked)">
                            <span x-text="m.name"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>
        <button type="button" @click="teams.push({name:'Tim '+(teams.length+1), member_ids:[]})"
                class="w-full py-3 rounded-xl border-2 border-dashed text-sm font-bold min-h-[48px]">+ Tim</button>
        <button type="submit" class="w-full py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">Simpan tim</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
function teamBuilder(initial, students) {
    return {
        teams: initial,
        students,
        toggle(ti, id, checked) {
            const arr = this.teams[ti].member_ids;
            const i = arr.indexOf(id);
            if (checked && i < 0) arr.push(id);
            if (!checked && i >= 0) arr.splice(i, 1);
        },
    };
}
</script>
@endpush
