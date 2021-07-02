<?php
$file_url = '';

if ( isset($_GET['file']) ) {
	$file_url = $_GET['file'];
	header('Content-Type: application/force-download');
	header("Content-Transfer-Encoding: Binary");
	header("Content-disposition: attachment; filename=\"" . basename($file_url) . "\"");
	readfile($file_url); // do the double-download-dance (dirty but worky)
	exit();
} else {
	header("Location: ./");
	exit();
}

