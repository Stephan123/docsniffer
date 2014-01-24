<?php
/**
 * Suchmaske
 *
 *
 * @author Stephan Krauss
 * @date 21.05.13
 * @time 21:51
 *
 * tool
 */

include_once("define.php");

$datensaetze = false;

class index extends define
{

    private $_suchString = null;
    private $_result = array();

    private $flagSearchFront = false;
    private $flagSearchAdmin = false;
    private $flagSearchTool = false;
    private $flagSearchSound = false;

    public function __construct()
    {
        $this->_db_connect = mysqli_connect(
            $this->_db_server,
            $this->_db_user,
            $this->_db_passwort,
            $this->_db_datenbank
        );

        // mysqli_set_charset($this->_db_connect, "utf8");

        return;
    }

    /**
     * @param $suche
     *
     * @return index
     */
    public function setSuchstring($suche)
    {
        $this->_suchString = $suche;

        return $this;
    }

    public function setSearchFront()
    {
        $this->flagSearchFront = true;
    }

    public function setSearchAdmin()
    {
       $this->flagSearchAdmin = true;
    }

    public function setSearchTool()
    {
        $this->flagSearchTool = true;
    }

    public function setSearchSound()
    {
        $this->flagSearchSound = true;
    }

    /**
     * Ermittelt die Datensätze
     *
     * @return $this
     */
    public function findeDateien()
    {
        $sql = $this->erstellenSuchString($this->_suchString);

        // wenn keine Suchbegriffe
        if(empty($sql))
            return;

        if ($result = mysqli_query($this->_db_connect, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->_result[] = $row;
            }

            mysqli_free_result($result);
        }

        return $this;
    }

    /**
     * Umwandeln der Suchwörter nach 'Kölner Phonetic'
     *
     * @param $word
     * @return mixed
     */
    protected function cologne_phon($word) {
        /**
         * @param  string  $word string to be analyzed
         * @return string  $value represents the Kölner Phonetik value
         * @access public
         */

        // prepare for processing
        $word = strtolower($word);
        $substitution = array(
            "ä"=>"a",
            "ö"=>"o",
            "ü"=>"u",
            "ß"=>"ss",
            "ph"=>"f"
        );

        foreach ($substitution as $letter => $substitution) {
            $word = str_replace($letter,$substitution,$word);
        }

        $len = strlen($word);

        // Rule for exeptions
        $exceptionsLeading = array(
            4 => array("ca","ch","ck","cl","co","cq","cu","cx"),
            8 => array("dc","ds","dz","tc","ts","tz")
        );

        $exceptionsFollowing = array("sc","zc","cx","kx","qx");

        //Table for coding
        $codingTable = array(
            0  => array("a", "e", "i", "j", "o", "u", "y"),
            1  => array("b", "p"),
            2  => array("d", "t"),
            3  => array("f", "v", "w"),
            4  => array("c", "g", "k", "q"),
            48 => array("x"),
            5  => array("l"),
            6  => array("m", "n"),
            7  => array("r"),
            8  => array("c", "s", "z"),
        );

        for ($i=0; $i<$len; $i++) {
            $value[$i] = "";

            //Exceptions
            if ($i == 0 && $word[$i].$word[$i+1] == "cr") {
                $value[$i] = 4;
            }

            foreach ($exceptionsLeading as $code => $letters) {
                if (in_array($word[$i].$word[$i+1], $letters)) {
                    $value[$i] = $code;
                }
            }

            if ($i != 0 && (in_array($word[$i-1].$word[$i], $exceptionsFollowing))) {
                $value[$i] = 8;
            }

            // normal encoding
            if ($value[$i] == "") {
                foreach ($codingTable as $code => $letters) {
                    if (in_array($word[$i], $letters)) {
                        $value[$i] = $code;
                    }
                }
            }
        }

        // delete double values
        $len = count($value);

        for ($i=1; $i<$len; $i++) {
            if ($value[$i] == $value[$i-1]) {
                $value[$i] = "";
            }
        }

        // delete vocals
        for ($i=1; $i>$len; $i++) {
            // omitting first characer code and h
            if ($value[$i] == 0) {
                $value[$i] = "";
            }
        }

        $value = array_filter($value);
        $value = implode("", $value);

        return $value;
    }
	
	/**
	* Erstellen der 'unscharfen' Suche
	*
	* + Verknüpft mehrere Suchbegriffe und sucht mit 'like'
	*/
	public function erstellenSuchString($suchstring)
	{
		$suchstring = trim($suchstring);
		$suchbegriffe = null;
		$suchbegriffe = explode(" ", $suchstring);

        $gefilterteSuchbegriffe = array();
        for($i=0; $i < count($suchbegriffe); $i++){
            if(!empty($suchbegriffe[$i]))
                $gefilterteSuchbegriffe[] = $suchbegriffe[$i];
        }

        // Kölner Phonetik
        if($this->flagSearchSound){
            for($i = 0; $i < count($gefilterteSuchbegriffe); $i++){
                $gefilterteSuchbegriffe[$i] = $this->cologne_phon($gefilterteSuchbegriffe[$i]);
            }
        }

        // erstellt SQL
        if(count($gefilterteSuchbegriffe) > 0){
            $sql = $this->ermittelnSqlZurSuche($gefilterteSuchbegriffe);

            return $sql;
        }
        else
            return false;
    }

    /**
     * @return array
     */
    public function getDatensaetze()
    {

        return $this->_result;
    }

    /**
     * @param $suchstring
     * @param $gefilterteSuchbegriffe
     * @return bool|string
     */
    protected function ermittelnSqlZurSuche($gefilterteSuchbegriffe)
    {
        // ein oder mehrere Suchbegriffe
        $sql = "SELECT
                bereich,
                datei,
                geaendert,
                count(id) as anzahl
            FROM
                klassenverwaltung where ";

        for ($i = 0; $i < count($gefilterteSuchbegriffe); $i++) {
            if (strlen($gefilterteSuchbegriffe[$i]) > 2)

                // Kölner Dialekt
                if($this->flagSearchSound)
                    $sql .= "klassenbeschreibung SOUNDEX('" .$gefilterteSuchbegriffe[$i] ."') and ";
                // normale Suchwörter
                else
                    $sql .= "klassenbeschreibung LIKE '%" . $gefilterteSuchbegriffe[$i] . "%' and ";
        }

        $sql = substr($sql, 0, -4);


        if ($this->flagSearchFront)
            $sql .= " and bereich = 'front'";

        if ($this->flagSearchAdmin)
            $sql .= " and bereich = 'admin'";

        if ($this->flagSearchTool)
            $sql .= " and bereich = 'tool'";

        $sql .= " group by datei";
        $sql .= " order by anzahl desc";

        return $sql;
    }
}

if (isset($_POST['suche'])) {

    $suche = new index();
    $suche->setSuchstring($_POST['suche']);

    if((array_key_exists('front', $_POST)) and ($_POST['front'] == 'front'))
        $suche->setSearchFront();

    if((array_key_exists('admin', $_POST)) and ($_POST['admin'] == 'admin'))
        $suche->setSearchAdmin();

    if((array_key_exists('tool', $_POST)) and ($_POST['tool'] == 'tool'))
        $suche->setSearchTool();

    if((array_key_exists('sound', $_POST)) and ($_POST['sound'] == 'sound'))
            $suche->setSearchSound();

    $suche->findeDateien();

    $datensaetze = $suche->getDatensaetze();
    $suche = $_POST['suche'];

}
else {
    $suche = "";
}

?>
<p>&nbsp;</p>
<html>
<head>
    <title></title>
</head>
<body>
<table border="1" style="margin-left: 100px;">
    <tr>
        <td>Suche:</td>
        <td>
            <form method="post" action="index.php"><input type="text" name="suche" value="<?php echo $suche; ?>" style="border: 1px solid green; width: 500px;">
        </td>
    </tr>
    <tr>
        <td colspan="2">
            &nbsp; Admin:
            <input type="checkbox" value="admin" name="admin">
            &nbsp; Front: &nbsp;
            <input type="checkbox" name="front" value="front">
            &nbsp; Tool: &nbsp;
            <input type="checkbox" name="tool" value="tool">
            &nbsp; Soundsuche: &nbsp;
            <input type="checkbox" name="sound" value="sound">

        </td>
    </tr>
    <tr>
        <td colspan="2">
            <input type="submit" name="suchen"></form>
        </td>
    </tr>
</table>
</body>
</html>

<p>&nbsp;</p>

<table border="1" style="margin-left: 100px;">
    <tr>
		<td>&nbsp; Treffer &nbsp;</td>
        <td>&nbsp; Bereich &nbsp;</td>
        <td>&nbsp; Datei &nbsp;</td>
        <td>&nbsp; ge&auml;ndert &nbsp;</td>
        <td>&nbsp; Dokumentation &nbsp; </td>
    </tr>
<?php
if (is_array($datensaetze)) {

    for ($i = 0; $i < count($datensaetze); $i++) {

        if(array_key_exists('treffer', $datensaetze[$i]))
            $treffer = $datensaetze[$i]['treffer'] * 10;
        else
            $treffer = 1;

        $datei = substr($datensaetze[$i]['datei'], 0, -4);

        if ($datensaetze[$i]['bereich'] == 'front') {
            $dokumentation = "Front_Model_" . $datei;
        } elseif ($datensaetze[$i]['bereich'] == 'admin') {
            $dokumentation = "Admin_Model_" . $datei;
        } elseif ($datensaetze[$i]['bereich'] == 'tool') {
            $dokumentation = "nook_" . $datei;
        } else {
            $dokumentation = "plugin_" . $datei;
        }

        echo "<tr><td>".$datensaetze[$i]['anzahl']."</td><td>&nbsp; " .$datensaetze[$i]['bereich']. " &nbsp;</td><td>&nbsp; " . $datensaetze[$i]['datei']
            . " &nbsp;</td><td>&nbsp;".$datensaetze[$i]['geaendert']."&nbsp;</td><td>&nbsp; <a style='text-decoration: none; color: blue;' href='http://localhost/hob/_docs/class-"
            . $dokumentation . ".html' target='_blank'> zur Dokumentation </a> &nbsp;</td></tr> \n";
    }
}

?>
</table>