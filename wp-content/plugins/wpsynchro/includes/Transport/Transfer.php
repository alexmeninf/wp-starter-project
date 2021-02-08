<?php

namespace WPSynchro\Transport;

/**
 * Class to handle the transfer of data, both JSON and binary
 *
 * @since 1.3.0
 */
class Transfer
{

    public $boundary = "";
    public $should_deflate = false;
    public $should_encrypt = false;
    public $encryption_key = "";
    private $encryption_iv = "";
    public $files = array();
    public $requestsize = 0;
    public $dataobject = null;

    // Constants
    const APPROX_OVERHEAD_PER_FILE = 500;

    function __construct()
    {
        $this->boundary = "----" . bin2hex(openssl_random_pseudo_bytes(16));
        $this->dataobject = new \stdClass();
    }

    public function setDataObject($object)
    {
        $this->dataobject = $object;
        $this->requestsize += strlen(serialize($object));
    }

    public function getDataObject()
    {
        return $this->dataobject;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function setShouldEncrypt($encrypt)
    {
        $this->should_encrypt = $encrypt;
    }

    public function setEncryptionKey($key)
    {
        $this->encryption_key = $key;
    }

    public function setShouldDeflate($deflate)
    {
        $this->should_deflate = $deflate;
    }

    public function getRequestSize()
    {
        return $this->requestsize;
    }

    public function getFileOverhead()
    {
        return self::APPROX_OVERHEAD_PER_FILE;
    }

    public function addFiledata(\WPSynchro\Transport\TransferFile $file)
    {
        $duplicate_transferfile  = new TransferFile();
        $duplicate_transferfile->key = $file->key;
        $duplicate_transferfile->data = $file->data;

        $this->files[] = $duplicate_transferfile;
        $this->requestsize += strlen($file->data) + self::APPROX_OVERHEAD_PER_FILE;
    }

    public function getDataString()
    {
        $data = $this->generateDataString();

        // Options
        $dataheader = "WPSYNCHROTRANSFER:OPTIONS";
        if ($this->should_deflate) {
            $dataheader .= ":DEFLATE";
            $data = gzdeflate($data);
        }
        if ($this->should_encrypt && strlen($this->encryption_key) > 0) {
            $dataheader .= ":ENCRYPT";
            $iv_length = openssl_cipher_iv_length("AES128");
            $this->encryption_iv = openssl_random_pseudo_bytes($iv_length);
            $data = openssl_encrypt($data, "AES128", $this->encryption_key, OPENSSL_RAW_DATA, $this->encryption_iv);
            $data = $this->encryption_iv . $data;
        }
        $dataheader .= ":OPTIONSEND";

        // Data boundary
        $dataheader .= ":BOUNDARY:" . $this->boundary . ":BOUNDARYEND";

        return $dataheader . $data;
    }

    public function generateDataString()
    {

        $data = "";
        $data .= serialize($this->dataobject);
        $data .= $this->boundary;

        foreach ($this->files as $file) {

            $data .= "--FILEMETA--KEY" . $file->key;
            $data .= "--ENDFILEMETA--";

            $data .= $file->data;

            $data .= $this->boundary;
        }
        return $data;
    }

    public function getContentType()
    {
        return "application/octet-stream";
    }

    public function populateFromString($data)
    {
        // Should contain the proper start
        if (substr($data, 0, 17) != "WPSYNCHROTRANSFER") {
            return false;
        }

        // Get header data
        $header_end_position = strpos($data, "OPTIONSEND");
        if ($header_end_position === false) {
            return false;
        }

        $header_start = 26;
        $header_length = $header_end_position - $header_start - 1;
        $headerdata = substr($data, $header_start, $header_length);

        $options = explode(":", $headerdata);

        if (in_array("DEFLATE", $options)) {
            $this->should_deflate = true;
        }
        if (in_array("ENCRYPT", $options)) {
            $this->should_encrypt = true;
        }

        // Get boundary
        $boundary_start = strpos($data, "BOUNDARY:") + 9;
        $boundary_end = strpos($data, ":BOUNDARYEND");

        $boundary = substr($data, $boundary_start, ($boundary_end - $boundary_start));

        if (strlen($boundary) == 36) {
            $this->boundary = $boundary;
        } else {
            return false;
        }

        // Extract body
        $real_body_start = $boundary_end + 12;
        $data = substr($data, $real_body_start);

        if ($this->should_encrypt) {
            // Decrypt
            $iv_length = openssl_cipher_iv_length("AES128");
            $this->encryption_iv = substr($data, 0, $iv_length);
            $data = substr($data, $iv_length);
            $data = openssl_decrypt($data, "AES128", $this->encryption_key, OPENSSL_RAW_DATA, $this->encryption_iv);
        }
        if ($this->should_deflate) {
            // We just use built in with WP for decompression, as that handles all kinds of cases
            $data =  \WP_Http_Encoding::decompress($data);
            // If decompress fails
            if ($data === false) {
                return;
            }
        }

        // Data body is ready, decrypted and inflated, ready for action
        $parts = explode($this->boundary, $data);

        // First part is serialized
        $this->dataobject = unserialize($parts[0]);

        // Parts after that is files
        if (count($parts) == 1) {
            return true;
        }

        foreach ($parts as $partkey => $filepart) {
            if ($partkey == 0) {
                continue;
            }
            if (strlen($filepart) == 0) {
                continue;
            }

            // Handle file
            $filemeta_end = strpos($filepart, "--ENDFILEMETA--");
            $filemeta = substr($filepart, 12, $filemeta_end - 12);

            // Extract meta
            $meta_parts = explode("--", $filemeta);

            $fileobject = new TransferFile();
            $fileobject->key = intval(substr($meta_parts[0], 3));
            $file_data_start = $filemeta_end + 15;
            $fileobject->data = substr($filepart, $file_data_start);

            $this->files[] = $fileobject;
        }

        return true;
    }
}
