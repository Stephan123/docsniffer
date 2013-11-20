<?php
/**
 * Klasse zur Auswertung der Doc's der Model in einem Verzeichnis
 *
 *  CREATE TABLE `klassenverwaltung` (
 *    `id` int(10) NOT NULL AUTO_INCREMENT,
 *    `bereich` varchar(50) NOT NULL,
 *    `datei` varchar(50) NOT NULL,
 *    `klassenbeschreibung` text NOT NULL,
 *    `geaendert` datetime NOT NULL,
 *    `eingetragen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *    PRIMARY KEY (`id`),
 *    FULLTEXT KEY `volltext` (`klassenbeschreibung`)
 *  ) ENGINE=MyISAM AUTO_INCREMENT=2629 DEFAULT CHARSET=utf8
 *
 * @author  User
 * @date 20.05.13
 * @time 20:30
 *
 * @package tool
 */

include_once("define.php");
include_once("verzeichnisse.php");

class auswertungDocs extends define
{

    private $file = null;
    private $dirName = null;
    private $kennung = null;
    private $tokens = array();
    private $docs = array();
    private $_source = null;
    private $aenderungDatum = null;

    private $_zaehler = 0;

    public function __construct()
    {
        $this->_db_connect = mysqli_connect(
            $this->_db_server, $this->_db_user, $this->_db_passwort, $this->_db_datenbank
        );

        mysqli_set_charset($this->_db_connect, "utf8");

        $sql = "truncate table klassenverwaltung";
        mysqli_query($this->_db_connect, $sql);

        return;
    }

    /**
     * Setzt die Datei
     *
     * @param $file
     *
     * @return auswertungDocs
     */
    private function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Setzt das Verzeichnis
     *
     * @param $dir
     *
     * @return auswertungDocs
     */
    public function setDirName($dir)
    {
        $this->dirName = $dir;

        return $this;
    }

    /**
     * @param $dir
     *
     * @return auswertungDocs
     */
    private function setDir($dir)
    {
        $this->dirName = $dir;

        return $this;
    }

    /**
     * @param $kennung
     *
     * @return auswertungDocs
     */
    private function setKennung($kennung)
    {
        $this->kennung = $kennung;

        return $this;
    }

    /**
     * Übernimmt auszuwertende Datei
     *
     * @param $file
     *
     * @return auswertungDocs
     */
    private function
    setTokens()
    {
        $source = file_get_contents($this->dirName . "/" . $this->file);
        $this->tokens = token_get_all($source);

        $this->aenderungDatum = date('Y-m-d H:i:s', filemtime($this->dirName . "/" . $this->file));

        $this->_source = $source;

        return $this;
    }

    /**
     * Findet die Kommentare
     *
     * @return auswertungDocs
     */
    public function findComments()
    {
        $this->docs = array();
        $klassenName = "";

        // Klassenname
        preg_match('/class\s+(\w+)(.*)?\{/', $this->_source, $klassenTreffer);


        if(is_array($klassenTreffer)){
            if(isset($klassenTreffer[1])){
                $klassenName = str_replace("_"," ",$klassenTreffer[1]);
            }
        }

        foreach ($this->tokens as $token) {

            // Php Docs
            if ($token[0] == T_DOC_COMMENT) {
                $this->docs[] = $klassenName." ".$token[1];
            }
        }

        return $this;
    }

    /**
     * Ermitteln Beschreibung und Items der Klasse
     *
     * @return auswertungDocs
     */
    public function eintragenDocs()
    {

        foreach ($this->docs as $doc) {

            $doc = trim($doc);
            $this->_eintragenTabelle($doc);
        }

        return $this;
    }

    /**
     * Gibt den Zaehler der Einträge zurück
     *
     * @return int
     */
    public function getZaehler()
    {
        return $this->_zaehler;
    }

    /**
     * Findet die Kommentare der Datei
     */
    private function _findComments()
    {
        foreach ($this->tokens as $token) {
            if ($token[0] == T_DOC_COMMENT) {
                $this->docs = $token[1];
            }
        }
    }

    /**
     * Eintragen der Klassenbeschreibung
     *
     * @return auswertungDocs
     */
    private function _eintragenTabelle($doc)
    {

        $doc = str_replace("'", "", $doc);

        $sql = "insert into klassenverwaltung (bereich, datei, klassenbeschreibung, geaendert) values('" . $this->kennung . "','". $this->file . "','" . $doc . "', '".$this->aenderungDatum."')";
        if (mysqli_query($this->_db_connect, $sql)) {
            $this->_zaehler++;
        } else {
            echo $sql . "<hr>";
        }

        return $this;
    }

    /**
     * Einlesen der Verzeichnisse
     *
     * @param array $verzeichnisse
     *
     * @return $this
     */
    public function start(array $verzeichnisse)
    {
        $j = 1;
        for ($i = 0; $i < count($verzeichnisse); $i++) {
            if ($handle = opendir($verzeichnisse[$i]['pfad'])) {
                while (false !== ($file = readdir($handle))) {
                    if (($file == '.') or ($file == '..') or (is_dir($verzeichnisse[$i]['pfad'] . "/" . $file))) {
                        continue;
                    }

                    echo $j . ": " . $verzeichnisse[$i]['pfad'] . "/" . $file . "<br>";

                    $this->_start($verzeichnisse, $file, $i);

                    $j++;
                }
            }
        }

        return $this;
    }

    /**
     *
     *
     * @param array $verzeichnisse
     * @param       $file
     * @param       $i
     */
    private function _start(array $verzeichnisse, $file, $i)
    {
        $this
            ->setFile($file)
            ->setDir($verzeichnisse[$i]['pfad'])
            ->setKennung($verzeichnisse[$i]['kennung'])
            ->setTokens()
            ->findComments()
            ->eintragenDocs();
    }
}

$auswertung = new auswertungDocs();
$auswertung->start($verzeichnisse);