<?php
#fungsi
function check_url($details)
{
    $archive = 'https://archive.org/details';
    if (strpos($details, $archive) === false) return false;
    $details = trim($details);
    if (substr($details, -1) == '/') {
        $details = substr($details, 0, strlen($details) - 1);
        return check_url($details);
    } else {
        if (preg_match("#^$archive/([^\/]+)$#is", $details)) return filter_var($details, FILTER_VALIDATE_URL);
        else return false;
    }
}

function stop_proses($pesan)
{
    cek($pesan);
    exit;
}

function sedotPdf($details)
{
    // $details = "https://archive.org/details/chorouhjawahara";
    $details = check_url($details) ? $details : stop_proses('URL not valid');

    $html = file_get_contents($details);
    if (false === $html) cek("Sorry, failed to get content of $details. Please try again");

    require __DIR__ . '/simple_html_dom.php';
    $dom = new simple_html_dom($html);

    // find title
    $title = $dom->find('span[class="breaker-breaker"]', 0)->innertext;

    // find description
    $desc = $dom->find('div[id=descript]', 0)->innertext;
    $desc = str_replace('</div>', "\n", $desc);
    $desc = strip_tags($desc, '<a>');

    // get download page html
    $download = str_replace("https://archive.org/details", "https://archive.org/download", $details);
    $html = file_get_contents($download);
    if (false === $html) cek("Sorry, failed to get content of $download. Please try again");

    // get all pdf links
    preg_match_all('/href="(([^\.]+).pdf)"/', $html, $cocok);
    $array_link_pdf = $cocok[1];

    // send message
    $message = "<b>$title</b>\n$details\n$desc";
    $message = str_split($message, 4096)[0];
    $url = "https://api.telegram.org/bot" . TOKEN_BOT . "/sendMessage?disable_web_page_preview=1&parse_mode=html&text=" . urlencode($message) . "&chat_id=" . ID_CHANNEL;
    $res = json_decode(file_get_contents($url));

    // save data to database
    if ($res->ok) {
        $message_id = $res->result->message_id;
        // https://t.me/gratis_kitab/123
        $url_postingan = "https://t.me/" . USERNAME_CHANNEL . "/" . $message_id;
        $pesan_mau_disimpan = "<a href='$url_postingan'>$title</a>\n" . $desc;
        tambahKitab($pesan_mau_disimpan);
    } else {
        cek("Failed to send message to channel. Please contact admin");
    }

    // send pdf files to Telegram
    foreach ($array_link_pdf as $link_pdf) {
        if (strpos($link_pdf, '_text.pdf') !== false) continue;
        $document_url = "$download/$link_pdf";
        $url = "https://api.telegram.org/bot" . TOKEN_BOT . "/sendDocument?caption=" . urlencode($title) . "&chat_id=" . ID_CHANNEL . "&document=$document_url";
        $res = file_get_contents($url);
        if (false === $res) {
            cek("Failed to send document $document_url to channel. Please contact admin");
        }
    }
}

function sedothtml($url, $postfields = [], $headers = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html; #string
}

function bikin_file($sumber, $bid, $teks)
{
    $namafile = "{$sumber}_{$bid}.html";
    $f = fopen($namafile, 'w');
    $teks = "<meta name='viewport' content='initial-scale = 1.0, maximum-scale = 1.0, user-scalable = yes, width = device-width'><p lang='ar' dir='rtl' align='right'><style>a{text-decoration:none}</style>$teks";
    fwrite($f, $teks);
    fclose($f);
    Bot::sendDocument($namafile);
    unlink($namafile);
}

function cariKitab($text)
{
    #sumber: database sendiri
    Bot::sendChatAction('typing');
    $konten = file_get_contents(DBDUMP);
    $konten = explode(SEPARATOR_DBDUMP, $konten);
    $ada = false;
    
    foreach ($konten as $value) {
        if (strpos($value, $text) !== false){
            $ada = true;
            cek($value);
        }
    }

    # database sendiri (waqfeya)
    Bot::sendChatAction('typing');
    $items = explode(SEPARATOR_DATABASE_WAQFEYA, file_get_contents(DATABASE_WAQFEYA));
    $cari = preg_grep("/$text/", $items);
    $jumlah = count($cari);
    $hasil_wqf = '';
    if ($jumlah > 0) {
        foreach ($cari as $bid => $konten) {
            #$bid = $bid + 1;
            $x = ['target=_blank', 'style="color:#990000;"', 'target="_blank"'];
            $konten = str_replace($x, '', strip_tags($items[$bid], '<a>'));
            $hasil_wqf .= 'كتاب رقم: ';
            $hasil_wqf .= "<a href='https://waqfeya.com/book.php?bid=$bid'>" . $bid . "</a>\n" . $konten;
            cek($hasil_wqf);
        }
    }

    #sumber: waqfeyah.wordpress.com
    #$text = 'البخاري';
    Bot::sendChatAction('typing');
    $html = sedothtml("https://waqfeyah.wordpress.com/?s=" . $text);
    preg_match_all('/<h1 class="entry-title"><a href="(.*)" rel="bookmark">([^\<\>]+)\<\/a\>/i', $html, $ke);
    if (count($ke[1]) > 0) {
        $urls1 = $ke[1]; #array
        $juduls1 = $ke[2];
    } else {
        $urls1 = [];
        $juduls1 = [];
    }

    $i = 1;
    $array_urls2 = [];
    $array_juduls2 = [];
    while ($konten = sedothtml("https://waqfeyah.wordpress.com/page/$i/?s=" . urlencode($text))) {
        $i++;
        $encoded_text = urlencode($text);
        preg_match("/https\:\/\/waqfeyah\.wordpress\.com\/page\/($i)\/\?s\=$encoded_text/", $konten, $ke);
        # kalau ada halaman baru
        if (isset($ke[1])) {
            $html = sedothtml($ke[0]);
            # ambil url page2
            preg_match_all('/<h1 class="entry-title"><a href="(.*)" rel="bookmark">([^\<\>]+)\<\/a\>/i', $html, $ke);
            $array_urls2[] = $ke[1];
            $array_juduls2[] = $ke[2];
        }
        # kalau tidak ada halaman baru
        else {
            #$array_urls2 = [];
            #$array_juduls2 = [];
            break;
        }
    }

    if (count($array_urls2) > 0 and count($array_juduls2) > 0) {

        $urls2 = [];
        foreach ($array_urls2 as $array) {
            foreach ($array as $url) {
                $urls2[] = $url;
            }
        }

        $juduls2 = [];
        foreach ($array_juduls2 as $array) {
            foreach ($array as $judul) {
                $juduls2[] = $judul;
            }
        }
    } else {
        $urls2 = [];
        $juduls2 = [];
    }

    $urls = array_merge($urls1, $urls2);
    $juduls = array_merge($juduls1, $juduls2);
    $links = array_combine($urls, $juduls);
    $waqfeyah_wp = '';
    if (count($links) > 0) {

        $no = 1;
        foreach ($links as $url => $judul) {
            cek("<a href='$url'>$judul</a>");
            $waqfeyah_wp .= "$no. <a href='$url'>" . $judul . "</a><br>\n";
            $no++;
        }
        kirim_html("waqfeyah.wp", $waqfeyah_wp);
    }

    #sumber3
    Bot::sendChatAction('typing');
    $keyword = urlencode($text);
    $url = "http://maktabahkita.blogspot.com/search?q=$keyword";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true
    ]);
    $konten = curl_exec($ch);
    curl_close($ch);
    preg_match_all('/http([^\'\"\<\>\{\}\*\$\|]+)\.(pdf|doc|docx|zip|rar)/i', $konten, $ke);
    $maktabahkita = '';
    if (count($ke[0]) > 0) {

        $no = 1;
        foreach ($ke[0] as $link) {
            cek("<a href='$link'>$link</a>");
            $maktabahkita .= "$no. <a href='$link'>$link</a><br>\n";
            $no++;
        }
        kirim_html("maktabahkita.blogspot", $maktabahkita);
    }

    #sumber4 (archive)
    Bot::sendChatAction('typing');
    $query = urlencode($text);
    $url = "https://archive.org/search.php?query=$query";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true
    ]);
    $konten = curl_exec($ch);
    curl_close($ch);
    preg_match_all('/\<a href\="([^\>\<]+)" title\=/', $konten, $ke);
    $links = $ke[1];
    preg_match_all('/\<div class\="ttl"\>([^\<\>]+)\<\/div\>/', $konten, $ke);
    $juduls = $ke[1];

    $hasil = array_combine($links, $juduls);
    $total = count($hasil);
    $archive = '';
    if ($total > 0) {
        $no = 1;
        foreach ($hasil as $url => $judul) {
            $archive .= "$no. <a href='https://archive.org$url'>" . trim($judul) . "</a><br>\n";
            $no++;
        }
        kirim_html("archive.org", $archive);
    }

    #kumpulkan hasil
    $hasil = $hasil_wqf . $waqfeyah_wp . $maktabahkita . $archive;

    if (empty($hasil) and !$ada) return Bot::sendMessage("Maaf, tidak ditemukan.");
}

function tambahKitab($teks)
{
    if (!empty($teks) and preg_match('/http/i', $teks)) {
        $respon = file_put_contents(DBDUMP, $teks . SEPARATOR_DBDUMP, FILE_APPEND)
        ? "kitab berhasil ditambahkan\n"
        : "gagal menambahkan kitab\n";
        return cek("$respon:\n$teks", ['chat_id' => ADMIN_ID]);
    }
}

function cek($respon, $options = null)
{
    $options['parse_mode'] = isset($options['parse_mode']) ? $options['parse_mode'] : 'html';
    if (!empty($respon)) return proses($respon, $options);
}

function proses($respon, $options = null)
{
    if (strlen(strip_tags($respon)) <= 4096) {
        return Bot::sendMessage($respon, $options);
    } else {
        return kirim_html('file', $respon);
    }
}

function kirim_html(string $namafile, string $konten)
{
    $namafile = "$namafile.html";
    file_put_contents($namafile, "<html><head><meta name='viewport' content='initial-scale = 1.0, maximum-scale = 1.0, user-scalable = yes, width = device-width'><p lang='ar' dir='rtl' align='right'><style>a{text-decoration:none}</style></head><body>$konten</body></html>");
    $res = Bot::sendDocument($namafile);
    unlink($namafile);
    return $res;
}
