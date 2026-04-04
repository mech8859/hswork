<?php
/**
 * Google Drive API Helper (PHP 7.2 compatible, no SDK needed)
 * 使用 OAuth 2.0 Refresh Token 進行認證
 */
class GoogleDrive
{
    private $config;
    private $accessToken;
    private $tokenExpiry = 0;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/google.php';
    }

    /**
     * 取得有效的 Access Token（自動用 Refresh Token 更新）
     */
    public function getAccessToken()
    {
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        $tokenFile = $this->config['token_file'];
        if (!file_exists($tokenFile)) {
            throw new Exception('Google Drive 尚未授權，請先執行 OAuth 授權流程');
        }

        $tokenData = json_decode(file_get_contents($tokenFile), true);
        if (empty($tokenData['refresh_token'])) {
            throw new Exception('Token 檔案中沒有 refresh_token');
        }

        // 用 refresh_token 換取新的 access_token
        $postData = array(
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $tokenData['refresh_token'],
            'grant_type'    => 'refresh_token',
        );

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('更新 Access Token 失敗: ' . $response);
        }

        $result = json_decode($response, true);
        $this->accessToken = $result['access_token'];
        $this->tokenExpiry = time() + intval($result['expires_in']) - 60;

        return $this->accessToken;
    }

    /**
     * 用授權碼交換 Token（OAuth 回調時使用）
     */
    public function exchangeCodeForToken($code)
    {
        $postData = array(
            'code'          => $code,
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'grant_type'    => 'authorization_code',
        );

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('交換 Token 失敗: ' . $response);
        }

        $tokenData = json_decode($response, true);
        if (empty($tokenData['refresh_token'])) {
            throw new Exception('未取得 refresh_token，請撤銷授權後重試');
        }

        // 儲存 Token
        $tokenDir = dirname($this->config['token_file']);
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0755, true);
        }
        file_put_contents($this->config['token_file'], json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    /**
     * 建立 Google Drive 資料夾
     * @return string 資料夾 ID
     */
    public function createFolder($name, $parentId = null)
    {
        $token = $this->getAccessToken();

        $metadata = array(
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        );
        if ($parentId) {
            $metadata['parents'] = array($parentId);
        }

        $ch = curl_init('https://www.googleapis.com/drive/v3/files');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('建立資料夾失敗: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['id'];
    }

    /**
     * 上傳檔案到 Google Drive
     * @param string $filePath 本機檔案路徑
     * @param string $fileName 在 Drive 上的檔名
     * @param string|null $folderId 目標資料夾 ID
     * @return array 檔案資訊 (id, name, webViewLink)
     */
    public function uploadFile($filePath, $fileName = null, $folderId = null)
    {
        if (!file_exists($filePath)) {
            throw new Exception('檔案不存在: ' . $filePath);
        }

        $token = $this->getAccessToken();
        $fileName = $fileName ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileSize = filesize($filePath);

        // 小檔案用 multipart upload（< 5MB）
        if ($fileSize < 5 * 1048576) {
            return $this->uploadSmall($filePath, $fileName, $mimeType, $folderId, $token);
        }

        // 大檔案用 resumable upload
        return $this->uploadResumable($filePath, $fileName, $mimeType, $folderId, $token);
    }

    private function uploadSmall($filePath, $fileName, $mimeType, $folderId, $token)
    {
        $boundary = 'hswork_boundary_' . uniqid();

        $metadata = array('name' => $fileName);
        if ($folderId) {
            $metadata['parents'] = array($folderId);
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= file_get_contents($filePath) . "\r\n";
        $body .= "--{$boundary}--";

        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('上傳失敗: ' . $response);
        }

        return json_decode($response, true);
    }

    private function uploadResumable($filePath, $fileName, $mimeType, $folderId, $token)
    {
        $metadata = array('name' => $fileName);
        if ($folderId) {
            $metadata['parents'] = array($folderId);
        }

        // Step 1: 建立 resumable upload session
        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,webViewLink');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: ' . $mimeType,
            'X-Upload-Content-Length: ' . filesize($filePath),
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);

        // 取得 upload URI
        if (!preg_match('/Location:\s*(.+)/i', $headers, $matches)) {
            throw new Exception('無法取得 resumable upload URI');
        }
        $uploadUri = trim($matches[1]);

        // Step 2: 上傳檔案內容
        $fileData = file_get_contents($filePath);
        $ch = curl_init($uploadUri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: ' . $mimeType,
            'Content-Length: ' . strlen($fileData),
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Resumable 上傳失敗: ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 列出資料夾內的檔案
     */
    public function listFiles($folderId = null, $pageSize = 100)
    {
        $token = $this->getAccessToken();

        $query = "trashed=false";
        if ($folderId) {
            $query .= " and '{$folderId}' in parents";
        }

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q'        => $query,
            'pageSize' => $pageSize,
            'fields'   => 'files(id,name,mimeType,size,createdTime)',
            'orderBy'  => 'name',
        ));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('列出檔案失敗: ' . $response);
        }

        $result = json_decode($response, true);
        return $result['files'];
    }

    /**
     * 下載檔案
     */
    public function downloadFile($fileId, $savePath)
    {
        $token = $this->getAccessToken();

        $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . $fileId . '?alt=media');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('下載失敗: ' . $content);
        }

        file_put_contents($savePath, $content);
        return true;
    }

    /**
     * 刪除檔案
     */
    public function deleteFile($fileId)
    {
        $token = $this->getAccessToken();

        $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . $fileId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 204;
    }

    /**
     * 取得 OAuth 授權 URL
     */
    public function getAuthUrl()
    {
        $params = array(
            'client_id'     => $this->config['client_id'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope'         => implode(' ', $this->config['scopes']),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        );
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * 檢查是否已授權
     */
    public function isAuthorized()
    {
        $tokenFile = $this->config['token_file'];
        if (!file_exists($tokenFile)) {
            return false;
        }
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        return !empty($tokenData['refresh_token']);
    }

    /**
     * 取得或建立子資料夾（帶快取）
     * @param string $parentId 父資料夾 ID
     * @param string $name 子資料夾名稱
     * @return string 子資料夾 ID
     */
    public function getOrCreateSubFolder($parentId, $name)
    {
        // 用檔案快取子資料夾 ID
        $cacheFile = dirname($this->config['token_file']) . '/drive_subfolder_cache.json';
        $cache = array();
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($cache)) $cache = array();
        }

        $cacheKey = $parentId . '/' . $name;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $folderId = $this->createFolder($name, $parentId);
        $cache[$cacheKey] = $folderId;
        file_put_contents($cacheFile, json_encode($cache));
        return $folderId;
    }

    /**
     * 備份檔案到 Google Drive（非同步風格，失敗不影響主流程）
     * @param string $localPath 本機檔案路徑
     * @param string $driveType 類型（customers, cases, case_payments, staff, etc.）
     * @param string $subFolder 子資料夾名稱（如客戶ID、案件ID）
     * @param string|null $fileName Drive 上的檔名
     * @return string|null Drive 檔案 ID，失敗返回 null
     */
    public function backupFile($localPath, $driveType, $subFolder, $fileName = null)
    {
        try {
            $foldersFile = dirname($this->config['token_file']) . '/google_drive_folders.json';
            if (!file_exists($foldersFile)) {
                return null;
            }
            $folders = json_decode(file_get_contents($foldersFile), true);

            // 如果類型資料夾不存在，建立到 root 下
            if (!isset($folders[$driveType])) {
                if (!isset($folders['root'])) return null;
                $folders[$driveType] = $this->createFolder($driveType, $folders['root']);
                file_put_contents($foldersFile, json_encode($folders, JSON_PRETTY_PRINT));
            }

            $parentId = $folders[$driveType];

            // 有子資料夾就建立/取得
            if ($subFolder) {
                $parentId = $this->getOrCreateSubFolder($parentId, $subFolder);
            }

            $result = $this->uploadFile($localPath, $fileName, $parentId);
            return isset($result['id']) ? $result['id'] : null;
        } catch (Exception $e) {
            // 記錄錯誤但不中斷主流程
            error_log('GoogleDrive backupFile error: ' . $e->getMessage());
            return null;
        }
    }
}
