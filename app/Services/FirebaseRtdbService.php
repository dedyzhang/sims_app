<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Auth;

class FirebaseRtdbService
{
    private ?Database $database = null;
    private ?Auth $auth = null;
    private ?string $credentialsPath;
    private ?string $databaseUrl;

    public function __construct()
    {
        $this->credentialsPath = $this->resolvePath(config('services.firebase.rtdb_credentials'));
        $this->databaseUrl = config('services.firebase.database_url');
    }

    public function enabled(): bool
    {
        return $this->credentialsPath !== null 
            && is_file($this->credentialsPath) 
            && !empty($this->databaseUrl);
    }

    private function resolvePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $basePath = base_path($path);
        if (file_exists($basePath)) {
            return $basePath;
        }
        if (file_exists($path)) {
            return $path;
        }
        $appPath = storage_path('app/' . $path);
        if (file_exists($appPath)) {
            return $appPath;
        }
        return null;
    }

    private function getDatabase(): Database
    {
        if ($this->database === null) {
            $this->database = (new Factory)
                ->withServiceAccount($this->credentialsPath)
                ->withDatabaseUri($this->databaseUrl)
                ->createDatabase();
        }

        return $this->database;
    }

    private function getAuth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = (new Factory)
                ->withServiceAccount($this->credentialsPath)
                ->createAuth();
        }

        return $this->auth;
    }

    /**
     * Memperbarui/merge data pada path tertentu di Firebase RTDB.
     */
    public function update(string $path, array $data): void
    {
        if (! $this->enabled()) return;
        
        try {
            $this->getDatabase()->getReference($path)->update($data);
        } catch (\Exception $e) {
            \Log::error('Firebase RTDB Update Failed: ' . $e->getMessage());
        }
    }

    /**
     * Menimpa total data pada path tertentu di Firebase RTDB.
     */
    public function set(string $path, $value): void
    {
        if (! $this->enabled()) return;
        
        try {
            $this->getDatabase()->getReference($path)->set($value);
        } catch (\Exception $e) {
            \Log::error('Firebase RTDB Set Failed: ' . $e->getMessage());
        }
    }

    /**
     * Push data baru sebagai child dengan auto-generated ID (seperti insert log/chat).
     */
    public function push(string $path, array $data): void
    {
        if (! $this->enabled()) return;

        try {
            $this->getDatabase()->getReference($path)->push($data);
        } catch (\Exception $e) {
            \Log::error('Firebase RTDB Push Failed: ' . $e->getMessage());
        }
    }

    /**
     * Hapus data di path tertentu.
     */
    public function remove(string $path): void
    {
        if (! $this->enabled()) return;

        try {
            $this->getDatabase()->getReference($path)->remove();
        } catch (\Exception $e) {
            \Log::error('Firebase RTDB Remove Failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate token custom untuk klien JS dengan custom claims.
     */
    public function createCustomToken(string $uid, array $claims = []): ?string
    {
        if (! $this->enabled()) return null;

        try {
            return $this->getAuth()->createCustomToken($uid, $claims)->toString();
        } catch (\Exception $e) {
            \Log::error('Firebase Custom Token Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Menyimpan UUID user yang perlu di-ping pada akhir request (batching).
     */
    private static array $pendingUserPings = [];

    /**
     * Mengirim "ping" (timestamp) ke RTDB agar klien JS segera melakukan sinkronisasi
     * ulang (fetch) tanpa melakukan polling berkala.
     */
    public function pingUser(string $uuid): void
    {
        \Log::info("pingUser called for: " . $uuid);
        self::$pendingUserPings[$uuid] = true;

        static $registered = false;
        if (!$registered) {
            app()->terminating(function () {
                $this->flushPendingPings();
            });
            $registered = true;
        }
    }

    /**
     * Mengeksekusi semua ping user dalam satu HTTP request (multi-path update).
     */
    public function flushPendingPings(): void
    {
        if (empty(self::$pendingUserPings) || !$this->enabled()) {
            return;
        }

        $updates = [];
        $timestamp = now()->timestamp;
        
        foreach (array_keys(self::$pendingUserPings) as $uuid) {
            $updates["users/{$uuid}/sync_trigger"] = $timestamp;
        }

        \Log::info("Flushing Firebase pings for " . count($updates) . " users.");

        try {
            $this->getDatabase()->getReference()->update($updates);
            \Log::info("Firebase RTDB Batch Ping Success.");
        } catch (\Exception $e) {
            \Log::error('Firebase RTDB Batch Ping Failed: ' . $e->getMessage());
        }

        self::$pendingUserPings = [];
    }

    public function pingGroup(string $grupUuid): void
    {
        $this->set("groups/{$grupUuid}/sync_trigger", now()->timestamp);
    }

    public function pingConversation(string $conversationUuid): void
    {
        $this->set("conversations/{$conversationUuid}/sync_trigger", now()->timestamp);
    }

    public function pingChatbotAdmin(): void
    {
        $this->set("global/chatbot_admin/sync_trigger", now()->timestamp);
    }

    public function pingClassroomComment(string $uuid): void
    {
        $this->set("classroom_comments/{$uuid}/sync_trigger", now()->timestamp);
    }

    public function pingForumComment(string $topicUuid): void
    {
        $this->set("forum_comments/{$topicUuid}/sync_trigger", now()->timestamp);
    }

    public function pingArena(string $sessionId): void
    {
        $this->set("arena/{$sessionId}/sync_trigger", now()->timestamp);
    }

    public function pingArenaPractice(string $sessionId): void
    {
        $this->set("arena_practice/{$sessionId}/sync_trigger", now()->timestamp);
    }
}
