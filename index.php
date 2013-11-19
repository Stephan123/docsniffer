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
					geaendert,
					count(id) as anzahl
				FROM
					klassenverwaltung ";

            if($this->flagSearchSound)
                $sql .= "where klassenbeschreibung SOUNDS LIKE '%".$suchstring."%'";
            else
				$sql .= "WHERE klassenbeschreibung LIKE '%".$suchstring."%'";
		}
		else{
			$sql = "SELECT 
					bereich,
					datei,
					geaendert,
					count(id) as anzahl
				FROM
					klassenverwaltung where ";
					
			for($i=0; $i < count($suchbegriffe); $i++){
				if(strlen($suchbegriffe[$i]) > 2)

                    if($this->flagSearchSound)
                        $sql .= "klassenbeschreibung SOUNDS LIKE '%".$suchstring."%' and ";
                    else
                        $sql .= "klassenbeschreibung LIKE '%".$suchstring."%' and ";
			}
			
			$sql = substr($sql, 0 , -4);
		}

        if($this->flagSearchFront)
            $sql .= " and bereich = 'front'";

        if($this->flagSearchAdmin)
            $sql .= " and bereich = 'admin'";

        if($this->flagSearchTool)
            $sql .= " and bereich = 'tool'";

        $sql .= " group by datei";
		$sql .= " order by anzahl desc";

		return $sql;
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