<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pesan grup chat.
 *
 * CATATAN: sengaja TIDAK memakai trait SoftDeletes walau ada kolom deleted_at.
 * Pesan yang dimoderasi harus tetap muncul di urutan percakapan sebagai
 * "Pesan ini dihapus" — kalau baris hilang dari hasil query, urutan seq jadi
 * bolong dan klien mengira ada pesan yang gagal terkirim.
 */
class GrupChatMessage extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'grup_chat_messages';

    protected $fillable = [
        'grup_id', 'seq', 'user_id', 'sender_nama', 'sender_peran', 'body',
        'reply_to_id', 'reply_snippet', 'reply_nama',
        'attachment_path', 'attachment_type', 'attachment_name', 'attachment_size',
        'deleted_at', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'attachment_size' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function grup()
    {
        return $this->belongsTo(GrupChat::class, 'grup_id', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function isDihapus(): bool
    {
        return $this->deleted_at !== null;
    }

    /** Isi yang aman ditampilkan — pesan termoderasi tidak pernah membocorkan body asli. */
    public function bodyTampil(): ?string
    {
        return $this->isDihapus() ? 'Pesan ini dihapus.' : $this->body;
    }
}
