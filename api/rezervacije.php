<?php

  $DEBUG = true;
  include("orodja.php");
  $zbirka = dbConnect();

  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');	// Dovolimo dostop izven trenutne domene (CORS)
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

  switch($_SERVER["REQUEST_METHOD"]){

	case 'GET':
    session_start();
    uporabnikRezervacije($_SESSION['uporabnisko_ime']);
    break;

  case 'POST':
    rezerviraj();
    break;

  case 'DELETE':
    if (isset($_GET["id"])){
      izbrisiRezervacijo($_GET["id"]);
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


function uporabnikRezervacije($uporabnisko_ime){

  global $zbirka;
  $uporabnisko_ime = mysqli_escape_string($zbirka, $uporabnisko_ime);

  $odgovor = array();
  $poizvedba = "SELECT prevozi.kraj_odhoda, prevozi.kraj_prihoda, prevozi.cas_odhoda, rezervacije.st_oseb, rezervacije.nacin_placila, prevozi.voznik, rezervacije.id, rezervacije.id_prevoza FROM rezervacije LEFT OUTER JOIN prevozi ON prevozi.id = rezervacije.id_prevoza WHERE rezervacije.uporabnisko_ime = '$uporabnisko_ime' ORDER BY prevozi.cas_odhoda ASC";
  $rezultat = mysqli_query($zbirka, $poizvedba);
  while ($vrstica = mysqli_fetch_assoc($rezultat)) {
    $vrstica["cas_odhoda"] = date('d-m-Y H:i', strtotime($vrstica["cas_odhoda"]));
    $odgovor[] = $vrstica;
  }
  http_response_code(200);
  echo json_encode($odgovor);

}

function rezerviraj(){
  global $zbirka, $DEBUG;
  $podatki = json_decode(file_get_contents('php://input'), true);

  if(isset($podatki["imeinpriimek"], $podatki["email"], $podatki["tel"], $podatki["st_oseb"], $podatki["nacin_placila"], $podatki["id_prevoza"])){

    $imeinpriimek = mysqli_escape_string($zbirka, $podatki["imeinpriimek"]);
    $email = mysqli_escape_string($zbirka, $podatki["email"]);
    $tel = mysqli_escape_string($zbirka, $podatki["tel"]);
    $st_oseb = mysqli_escape_string($zbirka, $podatki["st_oseb"]);
    $nacin_placila = mysqli_escape_string($zbirka, $podatki["nacin_placila"]);
    $id_prevoza = mysqli_escape_string($zbirka, $podatki["id_prevoza"]);
    session_start();
    $uporabnisko_ime_prijavljenega = $_SESSION['uporabnisko_ime'];

    $poizvedba="INSERT INTO rezervacije (id, id_prevoza, uporabnisko_ime, imeinpriimek, email, tel, st_oseb, nacin_placila) VALUES (NULL, '$id_prevoza', '$uporabnisko_ime_prijavljenega', '$imeinpriimek', '$email', '$tel', '$st_oseb', '$nacin_placila')";

    if(mysqli_query($zbirka, $poizvedba)){

      $poizvedba = "SELECT prosta_mesta FROM prevozi WHERE id = '$id_prevoza'";
      $rezultat = mysqli_query($zbirka, $poizvedba);
      $vrstica = mysqli_fetch_assoc($rezultat);

      $nova_prosta_mesta = $vrstica["prosta_mesta"] - $st_oseb;
      $poizvedba="UPDATE prevozi SET prosta_mesta = '$nova_prosta_mesta' WHERE id = '$id_prevoza'";

      if(mysqli_query($zbirka, $poizvedba)){
        http_response_code(201);
      }
      else {
        http_response_code(500);
        if($DEBUG){
          pripravi_odgovor_napaka(mysqli_error($zbirka));
        }
      }
    }
    else{
      http_response_code(500);
      if($DEBUG){
        pripravi_odgovor_napaka(mysqli_error($zbirka));
      }
    }
  }
}

function izbrisiRezervacijo($id){

  global $zbirka, $DEBUG;
  $id = mysqli_escape_string($zbirka, $id);

  if (rezervacija_obstaja($id)){

    $poizvedba = "SELECT rezervacije.st_oseb, rezervacije.id_prevoza, prevozi.prosta_mesta FROM rezervacije LEFT OUTER JOIN prevozi ON prevozi.id = rezervacije.id_prevoza WHERE rezervacije.id = '$id' ";
    $rezultat = mysqli_query($zbirka, $poizvedba);
    $rezultat = mysqli_fetch_assoc($rezultat);
    $st_oseb = $rezultat["st_oseb"];
    $id_prevoza = $rezultat["id_prevoza"];
    $prosta_mesta = $rezultat["prosta_mesta"];

    $nova_prosta_mesta = $prosta_mesta + $st_oseb;
    $poizvedba="UPDATE prevozi SET prosta_mesta = '$nova_prosta_mesta' WHERE id = '$id_prevoza'";

    if(mysqli_query($zbirka, $poizvedba)){

      $poizvedba="DELETE FROM rezervacije WHERE id = '$id'";

      if(mysqli_query($zbirka, $poizvedba)) {
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
  else {
    http_response_code(404);
  }
}

?>
