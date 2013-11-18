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
    private $_typ = null;

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

    /**
     * @param $typ
     *
     * @return index
     */
    public function setTyp($typ)
    {
        $typ = trim($typ);
        $this->_typ = $typ;

        return $this;
    }

    /**
     * Ermittelt die Datensätze
     *
     * @return $this
     */
    public function findeDateien()
    {

        $sql
            = "SELECT
          bereich,
          datei,
          MATCH (klassenbeschreibung) AGAINST ('" . $this->_suchString . "') AS treffer,
          geaendert
        FROM
          klassenverwaltung
        WHERE MATCH (klassenbeschreibung) AGAINST ('" . $this->_suchString . "')";

        switch ($this->_typ) {
            case 'admin':
                $sql .= " and bereich = 'admin'";
                break;
            case 'front':
                $sql .= " and bereich = 'front'";
                break;
            case 'tool':
                $sql .= " and bereich = 'tool'";
                break;
            case 'heute':
                $sql = "select bereich, datei, geaendert from klassenverwaltung where geaendert like '".date("Y-m-d")."%'";
                break;
			case 'unscharf':
				$sql = $this->erstellenSuchString($this->_suchString);	
				break;
        }

        if ($result = mysqli_query($this->_db_connect, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->_result[] = $row;
            }

            mysqli_free_result($result);
        }

        return $this;
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
		
		if((is_null($suchbegriffe)) or (count($suchbegriffe) < 2)){
			$sql = "SELECT 
					bereich,
					datei,
					geaendert 
				FROM
					klassenverwaltung 
					WHERE klassenbeschreibung LIKE '%".$suchstring."%' group by datei asc";
		}
		else{
			$sql = "SELECT 
					bereich,
					datei,
					geaendert 
				FROM
					klassenverwaltung where";
					
			for($i=0; $i < count($suchbegriffe); $i++){
				if(strlen($suchbegriffe[$i]) > 2)
					$sql .= " klassenbeschreibung like '%".$suchbegriffe[$i]."%' and";
			}
			
			$sql = substr($sql, 0 , -4);
			$sql .= " group by datei asc";
		}

		return $sql;
	}

    /**
     * Durchsucht die Tabelle mittels Soundex
     *
     * @param $suchstring
     */
    public function soundex()
    {
        $sql = "
        SELECT
          bereich,
          datei,
          geaendert,
          klassenbeschreibung = 0 as treffer
        FROM
          klassenverwaltung
        WHERE SOUNDEX(klassenbeschreibung) LIKE CONCAT('%',SOUNDEX('".$this->_suchString."'),'%')";

        if ($result = mysqli_query($this->_db_connect, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->_result[] = $row;
            }

            mysqli_free_result($result);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getDatensaetze()
    {

        return $this->_result;
    }
}

if (isset($_POST['suche'])) {

    $suche = new index();
    $suche->setSuchstring($_POST['suche']);


    if($_POST['typ'] == 'soundex'){
        $suche->soundex();
    }
    else{
        $suche
           ->setTyp($_POST['typ'])
           ->findeDateien();
    }

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
			&nbsp; unscharf: &nbsp;
            <input type="radio" name="typ" value="unscharf" checked="true">
            &nbsp; Admin:
            <input type="radio" value="admin" name="typ">
            &nbsp; Front: &nbsp;
            <input type="radio" name="typ" value="front">
            &nbsp; Tool: &nbsp;
            <input type="radio" name="typ" value="tool">
            &nbsp; alles: &nbsp;
            <input type="radio" name="typ" value="alles">
            &nbsp; Heute: &nbsp;
            <input type="radio" name="typ" value="heute">
            &nbsp; Phonetisch: &nbsp;
            <input type="radio" name="typ" value="soundex">
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
        <td>&nbsp; Bereich &nbsp;</td>
        <td>&nbsp; Datei &nbsp;</td>
        <td>&nbsp; Treffer &nbsp;</td>
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

        $farbTreffer = (int) $treffer;

        $farbTreffer = 255 - ($farbTreffer * 3);
        if ($farbTreffer < 0) {
            $farbTreffer = 0;
        }

        echo "<tr><td>&nbsp; " .$datensaetze[$i]['bereich']. " &nbsp;</td><td>&nbsp; " . $datensaetze[$i]['datei']
            . " &nbsp;</td><td style='background-color:rgb(255," . $farbTreffer . ",0);'>&nbsp; " . number_format(
            $treffer, 2
        )
            . " % &nbsp;</td><td>&nbsp;".$datensaetze[$i]['geaendert']."&nbsp;</td><td>&nbsp; <a style='text-decoration: none; color: blue;' href='http://localhost/hob/_docs/class-"
            . $dokumentation . ".html' target='_blank'> zur Dokumentation </a> &nbsp;</td></tr> \n";
    }
}

?>
</table>