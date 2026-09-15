<?php
// PHP automatically decodes application/x-www-form-urlencoded
parse_str("teks_soal=PHA%2BU29hbCAxPC9wPg%3D%3D", $post);
echo $post["teks_soal"];

