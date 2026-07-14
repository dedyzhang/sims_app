<?php

namespace App\Support;

use App\Models\ChatbotMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Penyimpanan lampiran chatbot di disk privat + penghapusan legacy publik. */
class ChatAttachments
{
    public const PREFIX = 'chat/';

    public static function storeImage(UploadedFile $file): string
    {
        $name = (string) Str::uuid().'.'.Uploads::safeExtension($file, ['jpeg', 'jpg', 'png', 'webp'], 'jpg');
        $path = self::PREFIX.$name;
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public static function storeFile(UploadedFile $file): string
    {
        $ext = Uploads::safeExtension($file, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'], 'bin');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $folder = (string) Str::uuid();
        $filename = $base.'.'.$ext;
        $dir = self::PREFIX.$folder;
        Storage::disk('local')->putFileAs($dir, $file, $filename);

        return $dir.'/'.$filename;
    }

    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 'uploads/chat/')) {
            $full = public_path($path);
            if (File::exists($full)) {
                File::delete($full);
                $dir = dirname($full);
                if (basename($dir) !== 'chat') {
                    File::deleteDirectory($dir);
                }
            }

            return;
        }

        if (str_starts_with($path, self::PREFIX)) {
            Storage::disk('local')->delete($path);
            $dir = dirname($path);
            if ($dir !== self::PREFIX && Storage::disk('local')->exists($dir)) {
                $remaining = Storage::disk('local')->files($dir);
                if ($remaining === []) {
                    Storage::disk('local')->deleteDirectory($dir);
                }
            }
        }
    }

    public static function resolveAbsolutePath(string $path): ?string
    {
        if (str_starts_with($path, 'uploads/chat/')) {
            $full = public_path($path);

            return is_file($full) ? $full : null;
        }

        if (str_starts_with($path, self::PREFIX)) {
            $full = Storage::disk('local')->path($path);

            return is_file($full) ? $full : null;
        }

        return null;
    }

    public static function attachmentUrl(ChatbotMessage $message): ?string
    {
        if (! $message->attachment_path) {
            return null;
        }

        return route('chatbot.attachment', $message);
    }

    public static function userCanAccess(User $user, ChatbotMessage $message): bool
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->first();

        if (! $conversation) {
            return false;
        }

        if ($conversation->user_id === $user->getKey()) {
            return true;
        }

        return $user->isAdmin();
    }

    public static function downloadResponse(ChatbotMessage $message): BinaryFileResponse
    {
        $path = $message->attachment_path;
        abort_unless($path, 404);

        $absolute = self::resolveAbsolutePath($path);
        abort_unless($absolute, 404);

        return response()->download($absolute, basename($path));
    }
}
