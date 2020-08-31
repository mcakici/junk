<?php
class minify {
    private $files;
    private $filetype;
    private $txt_file_output;

    /* $fileTypes "js" "css"  */
    public function files(array $arrayFiles,$fileTypes = "js") {
        $this->files = $arrayFiles;
        $this->filetype = $fileTypes;
        $this->txt_file_output = "";
        return $this;
    }

    public function merge() {
        foreach ($this->files AS $filename) {
            $this->txt_file_output .= file_get_contents($filename, true);
        }
        return $this;
    }

    public function compress() {
        $path_minifyjs = ABSPATH . 'vendor/matthiasmullie';
        require_once $path_minifyjs . '/minify/src/Minify.php';
        require_once $path_minifyjs . '/minify/src/CSS.php';
        require_once $path_minifyjs . '/minify/src/JS.php';
        require_once $path_minifyjs . '/minify/src/Exception.php';
        require_once $path_minifyjs . '/minify/src/Exceptions/BasicException.php';
        require_once $path_minifyjs . '/minify/src/Exceptions/FileImportException.php';
        require_once $path_minifyjs . '/minify/src/Exceptions/IOException.php';
        require_once $path_minifyjs . '/path-converter/src/ConverterInterface.php';
        require_once $path_minifyjs . '/path-converter/src/Converter.php';
        

        $minifier = ($this->filetype == "js" ? new MatthiasMullie\Minify\JS() : new MatthiasMullie\Minify\CSS());
        foreach ($this->files as $key => $value) {
            $minifier->add($value);
        }
        $this->txt_file_output = $minifier->minify();
        return $this; 
    }

    public function save($newfilename) {
        $fp = fopen(ABSPATH.$newfilename,"wb");
	    $fpwritten = fwrite($fp,$this->txt_file_output);
        fclose($fp);
        if($fpwritten){
            return true;
        }else{
            return false;
        }
    }

}