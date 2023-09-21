<?php

require __DIR__ . '/config.php';

Bot::text(function ($text) {
    $message = Bot::message();
    $chat_id = Bot::from_id();
    $nama = Bot::user();
    $type = $message["chat"]["type"] ?? '';
    if ($type == "group" or $type == "supergroup" or $type == 'private') {
        if (preg_match('/^(\#kitab|كتاب)(.*)/i', $text, $bagian)) {
            $kitab = trim($bagian[2]);
            Bot::bg_exec('cariKitab', array($kitab), 'require "config.php";', 1000);
            return Bot::sendMessage('Tunggu sebentar...');
        }
    }

    if ($type == 'private') {

        # /start
        if ($text == '/start') {
            Bot::sendChatAction('typing');
            return cek("Assalamualaikum <a href='tg://user?id=$chat_id'>$nama</a>\n\nTulis nama kitab yang ingin anda cari", ['reply'=>true, 'parse_mode'=>'html']);
        }

        # /tambah kitab
        elseif (preg_match('/^\/tambah(.*)/is', $text, $ke)) {
            $teks = trim($ke[1]);
            return tambahKitab($teks);
        }

        # /pdf
        elseif (preg_match('/^\/?pdf(.*)/is', $text, $bagian)) {
            sedotPdf(trim($bagian[1]));
        }
    }
});

Bot::channel_post(function () {
    $channel = Bot::message();
    if ($channel["sender_chat"]["id"] == ID_CHANNEL) {
        #coba upload dokumen
        //upload dokumen
        if (isset($channel["document"])) {
            $document   = $channel["document"];
            #$doc_file_id  = $document["file_id"];
            $doc_file_name  = str_replace('_', ' ', $document["file_name"]);
            $id_postingan = $channel["message_id"];
            $username_channel = $channel["sender_chat"]["username"];
            $caption = $channel["caption"] ?? '';
            $url_postingan = "https://t.me/$username_channel/$id_postingan";
            $pesan_mau_disimpan = "<a href='$url_postingan'>$doc_file_name</a>\n" . $caption;
            tambahKitab($pesan_mau_disimpan);
        }

        #channel post text
        if (isset($channel["text"])) {
            $id_postingan = $channel["message_id"];
            $username_channel = $channel["sender_chat"]["username"];
            $text = $channel["text"];
            $url_postingan = "https://t.me/$username_channel/$id_postingan";
            $pesan_mau_disimpan = $text . "\n" . $url_postingan;
            tambahKitab($pesan_mau_disimpan);
        }
    }
});

Bot::run();
