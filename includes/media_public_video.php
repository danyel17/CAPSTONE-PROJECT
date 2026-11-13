<?php
/**
 * Resolve admin-uploaded videos from media_files for public pages.
 *
 * Handles Hostinger vs XAMPP path differences, section aliases
 * (Announcement Video vs Homepage Announcement Video), and rows whose
 * files PHP cannot stat but Apache can still serve.
 */

if (!function_exists('dpm_media_project_root')) {

    function dpm_media_project_root(): string
    {
        return dirname(__DIR__);
    }

    function dpm_media_public_prefix(): string
    {
        if (defined('PROMO_WEB_ROOT')) {
            $pr = constant('PROMO_WEB_ROOT');
            if (is_string($pr) && $pr !== '') {
                return rtrim($pr, '/') . '/';
            }
        }
        return '';
    }

    function dpm_media_video_mimes(): array
    {
        return [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            'qt' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
        ];
    }

    function dpm_media_video_mime_for(string $path, string $storedMime = ''): string
    {
        $storedMime = strtolower(trim($storedMime));
        if (strpos($storedMime, 'video/') === 0) {
            return $storedMime;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = dpm_media_video_mimes();
        return $map[$ext] ?? 'video/mp4';
    }

    function dpm_media_normalize_stored_path(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        $stored = str_replace('\\', '/', $stored);
        $stored = preg_replace('#/{2,}#', '/', $stored);
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        $stored = preg_replace('/[?#].*$/', '', $stored);
        if (preg_match('#(?:^|/)(assets/(?:videos|imgs)/[^/]+)$#i', $stored, $m)) {
            return $m[1];
        }
        $stored = ltrim($stored, '/');
        if ($stored === '' || strpos($stored, '..') !== false) {
            return '';
        }
        return $stored;
    }

    function dpm_media_candidate_abs_paths(string $rel): array
    {
        $root = dpm_media_project_root();
        $relFs = str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $base = basename($rel);
        $doc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';
        $out = [
            $root . DIRECTORY_SEPARATOR . $relFs,
            $rel,
            $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . $base,
        ];
        if ($doc !== '') {
            $out[] = $doc . DIRECTORY_SEPARATOR . $relFs;
            $out[] = $doc . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . $base;
        }
        $cwd = getcwd();
        if (is_string($cwd) && $cwd !== '') {
            $out[] = $cwd . DIRECTORY_SEPARATOR . $relFs;
        }
        return array_values(array_unique($out));
    }

    function dpm_media_web_path(string $rel, string $foundAbs = ''): string
    {
        if (preg_match('#^https?://#i', $rel)) {
            return $rel;
        }
        $webRel = $rel;
        if (!preg_match('#^assets/#i', $webRel)) {
            $base = $foundAbs !== '' ? basename($foundAbs) : basename($rel);
            if ($base !== '' && $base !== '.' && $base !== '..') {
                $webRel = 'assets/videos/' . $base;
            }
        }
        $parts = array_map('rawurlencode', explode('/', str_replace('\\', '/', $webRel)));
        return dpm_media_public_prefix() . implode('/', $parts);
    }

    function dpm_media_resolve_row(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $rel = dpm_media_normalize_stored_path((string) ($row['file_path'] ?? ''));
        if ($rel === '') {
            $name = trim((string) ($row['file_name'] ?? ''));
            if ($name !== '' && strpos($name, '..') === false) {
                $rel = 'assets/videos/' . basename(str_replace('\\', '/', $name));
            }
        }
        if ($rel === '') {
            return null;
        }

        $foundAbs = '';
        if (!preg_match('#^https?://#i', $rel)) {
            foreach (dpm_media_candidate_abs_paths($rel) as $cand) {
                if (is_file($cand) && is_readable($cand) && filesize($cand) > 0) {
                    $foundAbs = $cand;
                    break;
                }
            }
        }

        $publicSrc = dpm_media_web_path($rel, $foundAbs);
        $mediaId = isset($row['media_id']) ? (int) $row['media_id'] : 0;
        if ($mediaId > 0 && strpos($publicSrc, '://') === false) {
            $publicSrc .= (strpos($publicSrc, '?') === false ? '?' : '&') . 'v=' . $mediaId;
        }

        $row['public_src'] = $publicSrc;
        $row['public_mime'] = dpm_media_video_mime_for($foundAbs !== '' ? $foundAbs : $rel, (string) ($row['mime_type'] ?? ''));
        $row['abs_path'] = $foundAbs;
        return $row;
    }

    function dpm_media_ensure_table(?mysqli $conn): bool
    {
        if (!$conn) {
            return false;
        }
        $chk = @$conn->query("SHOW TABLES LIKE 'media_files'");
        if (!$chk || $chk->num_rows === 0) {
            return false;
        }
        $sec = @$conn->query("SHOW COLUMNS FROM media_files LIKE 'section'");
        if ($sec && $sec->num_rows === 0) {
            @$conn->query("ALTER TABLE media_files ADD COLUMN section VARCHAR(100) DEFAULT 'overview_video'");
        }
        return true;
    }

    function dpm_media_fetch_video(?mysqli $conn, array $sections): ?array
    {
        if (!$conn || !dpm_media_ensure_table($conn) || $sections === []) {
            return null;
        }

        $sections = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtolower(trim((string) $s));
        }, $sections))));
        if ($sections === []) {
            return null;
        }

        $sql = "SELECT * FROM media_files
                WHERE file_type = 'video'
                  AND LOWER(TRIM(section)) = ?
                  AND (is_active = 1 OR is_active IS NULL)
                ORDER BY display_order ASC, uploaded_at DESC, media_id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        foreach ($sections as $section) {
            $stmt->bind_param('s', $section);
            if (!$stmt->execute()) {
                continue;
            }
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $resolved = dpm_media_resolve_row($row);
                if ($resolved && !empty($resolved['public_src'])) {
                    $stmt->close();
                    return $resolved;
                }
            }
        }
        $stmt->close();

        $sql2 = "SELECT * FROM media_files
                 WHERE file_type = 'video'
                   AND LOWER(TRIM(section)) = ?
                 ORDER BY uploaded_at DESC, media_id DESC";
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2) {
            foreach ($sections as $section) {
                $stmt2->bind_param('s', $section);
                if (!$stmt2->execute()) {
                    continue;
                }
                $res = $stmt2->get_result();
                while ($res && ($row = $res->fetch_assoc())) {
                    $resolved = dpm_media_resolve_row($row);
                    if ($resolved && !empty($resolved['abs_path'])) {
                        $stmt2->close();
                        return $resolved;
                    }
                }
            }
            $stmt2->close();
        }

        return null;
    }

    function dpm_media_announcement_sections(): array
    {
        return [
            'homepage_announcement_video',
            'third_video',
            'announcement_video',
            'announcement',
            'announcements',
        ];
    }

    function dpm_media_fetch_announcement(?mysqli $conn, array $prefer = []): ?array
    {
        $prefer = array_values(array_filter($prefer));
        $sections = array_values(array_unique(array_merge($prefer, dpm_media_announcement_sections())));
        return dpm_media_fetch_video($conn, $sections);
    }
}
