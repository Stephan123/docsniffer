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

class index extends define{

    private $_suchString = null;
    private $_result = array();

    public function __construct(){
        $this->_db_connect = mysqli_connect(
            $this->_db_server, $this->_db_user, $this->_db_passwort, $this->_db_datenbank
        );

        // mysqli_set_charset($this->_db_connect, "utf8");

        return;
    }

    /**
     * @param $suche
     *
     * @return index
     */
    public function setSuchstring($suche){
        $this->_suchString = $suche;

        return $this;
    }

    /**
     * Ermittelt die Datensätze
     *
     * @return $this
     */
    public function findeDateien(){

        $sql = "SELECT
          bereich,
          datei,
          MATCH (klassenbeschreibung) AGAINST ('".$this->_suchString."') AS treffer
        FROM
          klassenverwaltung
        WHERE MATCH (klassenbeschreibung) AGAINST ('".$this->_suchString."')";

        if($result = mysqli_query($this->_db_connect, $sql)){


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
    public function getDatensaetze(){
        return $this->_result;
    }
}

if(isset($_POST['suche'])){
    $suche = new index();

    $datensaetze = $suche
        ->setSuchstring($_POST['suche'])
        ->findeDateien()
        ->getDatensaetze();
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
                <td>Suche: </td>
                <td><form method="post" action="index.php"><input type="text" name="suche" style="border: 1px solid green;"></td>
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
        <td>&nbsp; Link &nbsp; </td>
    </tr>
<?php
for($i=0; $i < count($datensaetze); $i++){
    $treffer = $datensaetze[$i]['treffer'] * 10;
    echo "<tr><td>&nbsp; ".$datensaetze[$i]['bereich']." &nbsp;</td><td>&nbsp; ".$datensaetze[$i]['datei']." &nbsp;</td><td>&nbsp; ".number_format($treffer,2)." % &nbsp;</td><td>&nbsp; Link &nbsp;</td></tr>";
}
?>
</table>