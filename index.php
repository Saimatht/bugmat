<?php
// 1. Zeitzone
date_default_timezone_set('Europe/Berlin');

// 2. Pfad-Logik
$baseDir = realpath('.'); 
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$currentDir = str_replace('\\', '/', $currentDir);
$currentDir = trim($currentDir, '/');
$realDir = empty($currentDir) ? $baseDir : realpath($baseDir . DIRECTORY_SEPARATOR . $currentDir);

if ($realDir === false || strpos($realDir, $baseDir) !== 0) {
    $realDir = $baseDir;
    $currentDir = ''; 
}

$ignore = ['.', '..', 'index.php', 'index.html', '.htaccess'];

// Hilfsfunktion für Dateigrößen
function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// 3. Funktion zum Einlesen des aktuellen Ordners
function getDirContents($dir, $baseDir, $ignore) {
    $files = [];
    foreach (scandir($dir) as $file) {
        if (in_array($file, $ignore)) continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($filePath);
        
        $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
        $relPath = str_replace('\\', '/', $relPath);
        
        $size = $isDir ? '' : formatBytes(filesize($filePath));
        
        $files[] = [
            'name' => $file,
            'path' => $relPath,
            'isDir' => $isDir,
            'mtime' => date('d.m.Y H:i', filemtime($filePath)),
            'size' => $size,
            'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
        ];
    }
    return $files;
}

// 4. Funktion für die globale Suche
function getAllFilesRecursive($dir, $baseDir, $ignore) {
    $result = [];
    $items = getDirContents($dir, $baseDir, $ignore);
    foreach ($items as $item) {
        $result[] = $item;
        if ($item['isDir']) {
            $subDir = $dir . DIRECTORY_SEPARATOR . $item['name'];
            $result = array_merge($result, getAllFilesRecursive($subDir, $baseDir, $ignore));
        }
    }
    return $result;
}

// AJAX Schnittstelle
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'currentDir' => $currentDir,
        'files' => getDirContents($realDir, $baseDir, $ignore)
    ]);
    exit;
}

$globalFiles = getAllFilesRecursive($baseDir, $baseDir, $ignore);
$currentFiles = getDirContents($realDir, $baseDir, $ignore);

// Breadcrumb HTML für den initialen Aufruf generieren (mit sicherer Pfad-Übergabe)
$breadcrumbHtml = '<a href="#" class="crumb-link" onclick="loadDirectory(\'\', event)">Home</a>';
if ($currentDir != '') {
    $parts = explode('/', $currentDir);
    $path = '';
    foreach ($parts as $part) {
        $path .= ($path === '' ? '' : '/') . $part;
        $jsPath = json_encode($path); // JSON.stringify verhindert Fehler bei Leerzeichen/Sonderzeichen
        $breadcrumbHtml .= '<span class="crumb-separator">/</span>';
        // Wichtig: Einfache Anführungszeichen um das onclick, um Konflikte mit dem JSON zu vermeiden
        $breadcrumbHtml .= '<a href="#" class="crumb-link" onclick=\'loadDirectory(' . $jsPath . ', event)\'>' . htmlspecialchars($part) . '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dateien</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --header-bg: #2c3e50;   
            --primary: #3498db;     
            --bg-color: #cbd5e1;    
            --container-bg: #f1f5f9;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
            --hover-bg: #e2e8f0;    
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-color); 
            border-radius: 8px;
        }
        ::-webkit-scrollbar-thumb {
            background-color: #94a3b8; 
            border-radius: 8px;
            border: 3px solid var(--bg-color);
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: #64748b; 
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding: 2.5rem 1rem;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--container-bg);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: var(--header-bg);
            color: white;
            border-bottom: 3px solid var(--primary); 
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            gap: 0.75rem;
        }

        /* Der neue Home-Button */
        .home-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            transition: all 0.2s ease;
            flex-shrink: 0;
            text-decoration: none;
        }

        .home-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .home-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            overflow-x: auto;
            white-space: nowrap;
            font-size: 1.05rem;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 2px;
        }

        .breadcrumbs::-webkit-scrollbar {
            display: none;
        }

        .crumb-link {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }

        .crumb-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .crumb-link:last-child {
            color: #ffffff;
            font-weight: 600;
            pointer-events: none;
        }

        .crumb-separator {
            color: #64748b;
            margin: 0 0.2rem;
            font-size: 0.9rem;
        }

        #clock {
            font-variant-numeric: tabular-nums;
            font-size: 0.9rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.1); 
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            flex-shrink: 0;
        }

        .search-container {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--container-bg);
        }

        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-muted);
        }

        #searchInput {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            background: #ffffff; 
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        #searchInput:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        }
        
        #searchInput:focus + .search-icon {
            color: var(--primary);
        }

        .list-wrapper {
            min-height: 400px;
            position: relative;
        }

        .file-list {
            list-style: none;
            transition: opacity 0.2s ease-in-out;
        }

        .file-list.is-loading {
            opacity: 0.5; 
            pointer-events: none;
        }

        .file-list li {
            border-bottom: 1px solid var(--border-color);
        }
        
        .file-list li:last-child {
            border-bottom: none;
        }

        .file-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 2rem;
            text-decoration: none;
            color: var(--text-main);
            transition: background-color 0.15s ease;
            cursor: pointer;
        }

        .file-row:hover {
            background-color: var(--hover-bg);
        }

        .file-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0; 
        }

        .icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            margin-right: 1rem;
            border-radius: 10px;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .file-row:hover .icon {
            transform: scale(1.05) rotate(-3deg);
        }

        .icon svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .icon-folder { background: #fef3c7; }
        .icon-folder svg { stroke: #d97706; fill: #fde68a; }
        
        .icon-pdf { background: #ffe4e6; }
        .icon-pdf svg { stroke: #e11d48; }
        .icon-pdf text { font-family: Arial, sans-serif; font-weight: bold; font-size: 7px; }
        
        .icon-image { background: #dcfce7; }
        .icon-image svg { stroke: #16a34a; }
        
        .icon-archive { background: #f3e8ff; }
        .icon-archive svg { stroke: #9333ea; }

        .icon-video { background: #ffedd5; }
        .icon-video svg { stroke: #ea580c; }

        .icon-audio { background: #fce7f3; }
        .icon-audio svg { stroke: #db2777; }

        .icon-word { background: #dbeafe; }
        .icon-word svg { stroke: #2563eb; }

        .icon-excel { background: #ccfbf1; }
        .icon-excel svg { stroke: #0d9488; }

        .icon-ppt { background: #ffedd5; }
        .icon-ppt svg { stroke: #ea580c; }

        .icon-text { background: #f1f5f9; }
        .icon-text svg { stroke: #475569; }

        .file-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-path-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
            display: none;
            align-items: center;
        }

        .search-mode .file-path-hint {
            display: flex;
        }

        .path-arrow {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-right: 4px;
            stroke: #94a3b8;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
        }
        
        .path-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem; 
            flex-shrink: 0;
            margin-left: 1rem;
        }

        .file-date {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            width: 130px;
            text-align: right;
        }

        .file-size {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            width: 60px;
            text-align: center; 
        }

        .placeholder {
            display: inline-block;
            width: 60px;
            height: 1px;
        }
        .placeholder-btn {
            display: inline-block;
            width: 32px; 
            height: 1px;
        }

        .btn-download-list {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: var(--text-muted);
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-download-list:hover {
            background: #cbd5e1;
            color: var(--primary);
        }

        .btn-download-list svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .back-link {
            background-color: #e0f2fe !important;
            color: var(--primary) !important;
            font-weight: 600;
        }
        .back-link:hover {
            background-color: #bae6fd !important;
        }
        .back-link .icon { background: transparent !important; }
        .back-link .icon svg { stroke: var(--primary) !important; }

        .no-results {
            padding: 4rem 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 1rem;
            display: none;
        }

        footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            background: var(--container-bg);
            border-top: 1px solid var(--border-color);
        }

        /* Mobile / Tablet */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            header { flex-direction: column; gap: 0.5rem; text-align: left; align-items: flex-start; }
            .header-left { width: 100%; }
        }
        @media (max-width: 600px) {
            body { padding: 0; background-color: var(--bg-color); }
            .container { border-radius: 0; min-height: 100vh; border: none; }
            header { padding: 1rem; }
            .file-row { padding: 0.8rem 1rem; }
            .file-date, .file-size, .placeholder { display: none; } 
            .file-path-hint { display: flex; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="header-left">
            <!-- Der klickbare Home-Button (ersetzt das statische Ordner-Icon) -->
            <a href="#" class="home-btn" onclick="loadDirectory('', event)" title="Zum Hauptverzeichnis">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </a>
            
            <div class="breadcrumbs" id="breadcrumbs">
                <?php echo $breadcrumbHtml; ?>
            </div>
        </div>
        <div id="clock">Lade Uhrzeit...</div>
    </header>

    <div class="search-container">
        <div class="search-wrapper">
            <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" name="search" placeholder="Nach Dateinamen suchen (auch in Unterordnern)...">
        </div>
    </div>

    <div class="list-wrapper">
        <div id="noResults" class="no-results">Keine Dateien gefunden.</div>
        <ul class="file-list" id="fileList"></ul>
    </div>

    <footer>
        bns1.he97.de &bull; Heger &copy; 2026
    </footer>
</div>

<script>
    const allFiles = <?php echo json_encode($globalFiles); ?>;
    
    let state = {
        currentDir: '<?php echo $currentDir; ?>',
        currentFiles: <?php echo json_encode($currentFiles); ?>,
        isSearching: false
    };

    const fileListEl = document.getElementById('fileList');
    const breadcrumbsEl = document.getElementById('breadcrumbs');
    const noResultsEl = document.getElementById('noResults');
    const searchInput = document.getElementById('searchInput');

    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const dateString = now.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
        document.getElementById('clock').textContent = `${dateString} | ${timeString}`;
    }
    updateClock();
    setInterval(updateClock, 1000);

    function updateBreadcrumbs(currentDir) {
        let html = `<a href="#" class="crumb-link" onclick="loadDirectory('', event)">Home</a>`;
        
        if (currentDir !== '') {
            const parts = currentDir.split('/');
            let path = '';
            parts.forEach(part => {
                path += (path === '' ? '' : '/') + part;
                const jsPath = JSON.stringify(path); // Sichere Übergabe des Pfades
                html += `<span class="crumb-separator">/</span>`;
                // Einfache Anführungszeichen für das onclick Attribut
                html += `<a href="#" class="crumb-link" onclick='loadDirectory(${jsPath}, event)'>${part}</a>`;
            });
        }
        
        breadcrumbsEl.innerHTML = html;
        
        setTimeout(() => {
            breadcrumbsEl.scrollLeft = breadcrumbsEl.scrollWidth;
        }, 10);
    }

    function getIconSVG(item) {
        if (item.isDir) {
            return '<svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>';
        }
        const ext = item.ext;
        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
        }
        if (['pdf'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><text x="5" y="18" fill="#e11d48" stroke="none">PDF</text></svg>';
        }
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
        }
        if (['mp4', 'mkv', 'avi', 'mov', 'webm'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><polygon points="10 9 15 12 10 15 10 9" fill="currentColor" stroke="none"></polygon></svg>';
        }
        if (['mp3', 'wav', 'ogg', 'm4a', 'flac'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>';
        }
        if (['doc', 'docx', 'odt', 'rtf'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><line x1="10" y1="9" x2="8" y2="9"></line></svg>';
        }
        if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>';
        }
        if (['ppt', 'pptx', 'odp'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>';
        }
        if (['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php'].includes(ext)) {
            return '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';
        }
        return '<svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>';
    }

    function getIconClass(item) {
        if (item.isDir) return 'icon-folder';
        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(item.ext)) return 'icon-image';
        if (['pdf'].includes(item.ext)) return 'icon-pdf';
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(item.ext)) return 'icon-archive';
        if (['mp4', 'mkv', 'avi', 'mov', 'webm'].includes(item.ext)) return 'icon-video';
        if (['mp3', 'wav', 'ogg', 'm4a', 'flac'].includes(item.ext)) return 'icon-audio';
        if (['doc', 'docx', 'odt', 'rtf'].includes(item.ext)) return 'icon-word';
        if (['xls', 'xlsx', 'csv', 'ods'].includes(item.ext)) return 'icon-excel';
        if (['ppt', 'pptx', 'odp'].includes(item.ext)) return 'icon-ppt';
        if (['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php'].includes(item.ext)) return 'icon-text';
        return '';
    }

    const downloadIconSVG = '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';

    function renderList(files, isSearch = false) {
        fileListEl.innerHTML = '';
        
        if (isSearch) {
            fileListEl.classList.add('search-mode');
        } else {
            fileListEl.classList.remove('search-mode');
        }
        
        if (files.length === 0) {
            noResultsEl.style.display = 'block';
            return;
        }
        noResultsEl.style.display = 'none';

        if (!isSearch && state.currentDir !== '') {
            const parentDir = state.currentDir.split('/').slice(0, -1).join('/');
            const li = document.createElement('li');
            li.innerHTML = `
                <div class="file-row back-link" onclick="loadDirectory('${parentDir}', event)">
                    <div class="file-left">
                        <span class="icon"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg></span>
                        <span class="file-info"><span class="file-name">Zurück</span></span>
                    </div>
                </div>
            `;
            fileListEl.appendChild(li);
        }

        files.forEach(item => {
            const li = document.createElement('li');
            const iconSvg = getIconSVG(item);
            const iconClass = getIconClass(item);
            
            let pathHint = '';
            if (isSearch && item.path.includes('/')) {
                let folderPath = item.path.split('/').slice(0, -1).join(' / ');
                if (folderPath) {
                    pathHint = `
                        <span class="file-path-hint">
                            <svg class="path-arrow" viewBox="0 0 24 24"><polyline points="15 10 20 15 15 20"></polyline><path d="M4 4v7a4 4 0 0 0 4 4h12"></path></svg>
                            <span class="path-text">${folderPath}</span>
                        </span>
                    `;
                }
            }
            
            let rowClickAction = '';
            let dateHTML = `<span class="file-date">${item.mtime}</span>`;
            let sizeHTML = '';
            let buttonHTML = '';

            if (item.isDir) {
                rowClickAction = `onclick="loadDirectory('${item.path}', event)"`;
                sizeHTML = `<span class="placeholder"></span>`;
                buttonHTML = `<span class="placeholder-btn"></span>`;
            } else {
                // Dateien öffnen sich nun immer in einem neuen Tab
                rowClickAction = `onclick="window.open('${item.path}', '_blank')"`;
                sizeHTML = `<span class="file-size">${item.size}</span>`;
                buttonHTML = `
                    <a href="${item.path}" download class="btn-download-list" title="Herunterladen" onclick="event.stopPropagation()">
                        ${downloadIconSVG}
                    </a>
                `;
            }

            li.innerHTML = `
                <div class="file-row" ${rowClickAction}>
                    <div class="file-left">
                        <span class="icon ${iconClass}">${iconSvg}</span>
                        <span class="file-info">
                            <span class="file-name">${item.name}</span>
                            ${pathHint}
                        </span>
                    </div>
                    <div class="file-actions">
                        ${dateHTML}
                        ${sizeHTML}
                        ${buttonHTML}
                    </div>
                </div>
            `;
            fileListEl.appendChild(li);
        });
    }

    async function loadDirectory(dir, event) {
        if (event) event.preventDefault();
        
        searchInput.value = '';
        state.isSearching = false;
        
        fileListEl.classList.add('is-loading');
        
        try {
            // Der Pfad wird hier nur EINMAL URL-kodiert
            const response = await fetch(`?ajax=1&dir=${encodeURIComponent(dir)}`);
            const data = await response.json();
            
            state.currentDir = data.currentDir;
            state.currentFiles = data.files;
            
            updateBreadcrumbs(data.currentDir);
            
            setTimeout(() => {
                renderList(state.currentFiles, false);
                fileListEl.classList.remove('is-loading');
            }, 150); 
            
        } catch (error) {
            console.error("Fehler beim Laden:", error);
            fileListEl.classList.remove('is-loading');
        }
    }

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        
        if (term === '') {
            if (state.isSearching) {
                state.isSearching = false;
                updateBreadcrumbs(state.currentDir);
                renderList(state.currentFiles, false);
            }
            return;
        }

        state.isSearching = true;
        breadcrumbsEl.innerHTML = '<span class="crumb-link" style="color: #fff; pointer-events: none;">Suchergebnisse</span>';
        
        const filtered = allFiles.filter(file => {
            const text = (file.name + ' ' + file.path).toLowerCase();
            return text.includes(term);
        });

        renderList(filtered, true);
    });

    renderList(state.currentFiles, false);
    
    setTimeout(() => {
        breadcrumbsEl.scrollLeft = breadcrumbsEl.scrollWidth;
    }, 50);
</script>

</body>
</html>
t