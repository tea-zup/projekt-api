<?php

  $DEBUG = true;
  include("orodja.php");
  $zbirka = dbConnect();

  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');	// Dovolimo dostop izven trenutne domene (CORS)
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

  switch($_SERVER["REQUEST_METHOD"]){

	case 'GET':

    if (isset($_GET["cas_odhoda"])){ // klice se filter prevozov
      filterPrevozi();
    }
    else if (isset($_GET["uporabnisko_ime"])) {
      session_start();
      uporabnikPonudbe($_SESSION['uporabnisko_ime']);
    }
    else if (isset($_GET["id"])) {
      pridobiPotnike($_GET["id"]);
    }
    else {
      vsiPrevozi(); //vsi prosti, ne pretekli prevozi
    }
	  break;

  case 'POST':
    dodajPrevoz();
    break;

  case 'DELETE':
    if (isset($_GET["id"])){
      izbrisiPrevoz($_GET["id"]);
    }
    else {
      http_response_code(400);	// Bad Request
    }
    break;

	case 'OPTIONS':
		http_response_code(204);
		break;

	default:
		http_response_code(405);		//'Method Not Allowed'
		break;
}

mysqli_close($zbirka);

function vsiPrevozi(){

  global $zbirka;

  $odgovor = array();
  $poizvedba = "SELECT kraj_odhoda, kraj_prihoda, cas_odhoda, voznik, cena, id, prosta_mesta FROM prevozi WHERE cas_odhoda > NOW() AND prosta_mesta > 0 ORDER BY kraj_odhoda ASC";
  $rezultat = mysqli_query($zbirka, $poizvedba);
  while ($vrstica = mysqli_fetch_assoc($rezultat)) {
    $vrstica["cas_odhoda"] = date('d-m-Y H:i', strtotime($vrstica["cas_odhoda"]));
    $odgovor[] = $vrstica;
  }

  http_response_code(200);
  echo json_encode($odgovor);
}

function filterPrevozi(){
  global $zbirka;

  if(isset($_GET["cas_odhoda"], $_GET["kraj_odhoda"], $_GET["kraj_prihoda"])){

    $cas_odhoda = mysqli_escape_string($zbirka, $_GET["cas_odhoda"]);
    $kraj_odhoda = mysqli_escape_string($zbirka, $_GET["kraj_odhoda"]);
    $kraj_prihoda = mysqli_escape_string($zbirka, $_GET["kraj_prihoda"]);

    $odgovor = array();
    $poizvedba = "SELECT kraj_odhoda, kraj_prihoda, cas_odhoda, voznik, cena, id, prosta_mesta FROM prevozi WHERE kraj_odhoda LIKE '$kraj_odhoda' AND kraj_prihoda LIKE '$kraj_prihoda' AND cas_odhoda > '$cas_odhoda' AND cas_odhoda > NOW() AND prosta_mesta > 0 ORDER BY kraj_odhoda ASC";
    $rezultat = mysqli_query($zbirka, $poizvedba);
    while ($vrstica = mysqli_fetch_assoc($rezultat)) {
      $odgovor[] = $vrstica; //cas odhoda je ze tipa date
    }

    http_response_code(200);
    echo json_encode($odgovor);
  }
}
function uporabnikPonudbe($uporabnisko_ime){

  global $zbirka;
  $uporabnisko_ime = mysqli_escape_string($zbirka, $uporabnisko_ime);

  $odgovor = array();
  $poizvedba = "SELECT id, kraj_odhoda, kraj_prihoda, cas_odhoda, cena, prosta_mesta FROM prevozi WHERE voznik = '$uporabnisko_ime' ORDER BY cas_odhoda ASC";
  $rezultat = mysqli_query($zbirka, $poizvedba);
  while ($vrstica = mysqli_fetch_assoc($rezultat)) {
    $sum = 0;
    $id_prevoza = $vrstica["id"];
    $poizvedba2 = "SELECT st_oseb FROM rezervacije WHERE id_prevoza = '$id_prevoza'";
    $rezultat2 = mysqli_query($zbirka, $poizvedba2);
    while ($vrstica2 = mysqli_fetch_assoc($rezultat2)) {
      $sum = $sum + $vrstica2["st_oseb"];
    }
    $vrstica["zasedena_mesta"] = $sum;
    $vrstica["cas_odhoda"] = date('d-m-Y H:i', strtotime($vrstica["cas_odhoda"]));
    $odgovor[] = $vrstica;
  }
  http_response_code(200);
  echo json_encode($odgovor);
}

function dodajPrevoz(){

  global $zbirka;
  $podatki = json_decode(file_get_contents('php://input'), true);

  if(isset($podatki["kraj_odhoda"], $podatki["kraj_prihoda"], $podatki["cas_odhoda"], $podatki["prosta_mesta"], $podatki["cena"])){
    $kraj_odhoda = mysqli_escape_string($zbirka, $podatki["kraj_odhoda"]);
    $kraj_prihoda = mysqli_escape_string($zbirka, $podatki["kraj_prihoda"]);
    $cas_odhoda = mysqli_escape_string($zbirka, $podatki["cas_odhoda"]);
    $prosta_mesta = mysqli_escape_string($zbirka, $podatki["prosta_mesta"]);
    $cena = mysqli_escape_string($zbirka, $podatki["cena"]);
    session_start();
    $voznik= $_SESSION['uporabnisko_ime'];

    $poizvedba="INSERT INTO prevozi (id, voznik, kraj_odhoda, kraj_prihoda, cas_odhoda, prosta_mesta, cena) VALUES (NULL, '$voznik', '$kraj_odhoda', '$kraj_prihoda', '$cas_odhoda', '$prosta_mesta', '$cena')";

    if(mysqli_query($zbirka, $poizvedba)){
      http_response_code(201);
    }
    else{
      http_response_code(500);
      if($DEBUG){
        pripravi_odgovor_napaka(mysqli_error($zbirka));
      }
    }
  }
}

function pridobiPotnike($id){

  global $zbirka;
  $id = mysqli_escape_string($zbirka, $id);

  $odgovor = array();
  $poizvedba = "SELECT * FROM rezervacije WHERE id_prevoza = '$id' ";
  $rezultat = mysqli_query($zbirka, $poizvedba);
  while ($vrstica = mysqli_fetch_assoc($rezultat)) {
    $odgovor[] = $vrstica; //cas odhoda je ze tipa date
  }

  http_response_code(200);
  echo json_encode($odgovor);
}
function izbrisiPrevoz($id){
  global $zbirka, $DEBUG;
  $id = mysqli_escape_string($zbirka, $id);

  if (prevoz_obstaja($id)){
    $poizvedba="DELETE FROM prevozi WHERE id = '$id'";

    if(mysqli_query($zbirka, $poizvedba)) {
      $poizvedba2="DELETE FROM rezervacije WHERE id_prevoza LIKE '$id'";

      if(mysqli_query($zbirka, $poizvedba2)) {
        http_response_code(204);
      }
      else {
        http_response_code(500);
      }
    }
    else {
      http_response_code(500);
    }
  }
}
?>
