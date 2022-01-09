<?php

  $DEBUG = true;
  include("orodja.php");
  $zbirka = dbConnect();

  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');	// Dovolimo dostop izven trenutne domene (CORS)
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

  switch($_SERVER["REQUEST_METHOD"]){

	case 'POST':
    $podatki = json_decode(file_get_contents('php://input'), true);
    if (isset($podatki["tip"])){
      $tip = mysqli_escape_string($zbirka, $podatki["tip"]);
      if ($tip == 'registracija'){
        registracija_uporabnika();
      }
      if ($tip == 'prijava'){
        prijava_uporabnika();
      }
    }
		break;

  case 'GET':
    if (isset($_GET["uporabnisko_ime"])){
      if ($_GET["uporabnisko_ime"] == "currentUser"){
        session_start();
        $_GET["uporabnisko_ime"] = $_SESSION['uporabnisko_ime'];
      }
      pridobi_uporabnika($_GET["uporabnisko_ime"]);
    }
    break;

  case 'PUT':
    posodobi_uporabnika();
    break;

	case 'OPTIONS':
		http_response_code(204);
		break;

	default:
		http_response_code(405);		//'Method Not Allowed'
		break;
}

mysqli_close($zbirka);

function pridobi_uporabnika($uporabnisko_ime){

  global $zbirka;

  $uporabnisko_ime = mysqli_escape_string($zbirka, $uporabnisko_ime);
  $poizvedba = "SELECT * FROM uporabniki WHERE uporabnisko_ime = '$uporabnisko_ime'";
  $rezultat = mysqli_query($zbirka, $poizvedba);
  $vrstica = mysqli_fetch_assoc($rezultat); //samo ena vrstica

  http_response_code(200);
  echo json_encode($vrstica);

}

function registracija_uporabnika(){

  global $zbirka, $DEBUG;
  $podatki = json_decode(file_get_contents('php://input'), true);

  if(isset($podatki["uporabnisko_ime"], $podatki["geslo"], $podatki["ime"], $podatki["priimek"], $podatki["email"])){
    $uporabnisko_ime = mysqli_escape_string($zbirka, $podatki["uporabnisko_ime"]);
    $geslo = password_hash(mysqli_escape_string($zbirka, $podatki["geslo"]), PASSWORD_DEFAULT);
    $ime = mysqli_escape_string($zbirka, $podatki["ime"]);
    $priimek = mysqli_escape_string($zbirka, $podatki["priimek"]);
    $email = mysqli_escape_string($zbirka, $podatki["email"]);

    if(!uporabnik_obstaja($uporabnisko_ime)){
      $poizvedba="INSERT INTO uporabniki (uporabnisko_ime, geslo, ime, priimek, email) VALUES ('$uporabnisko_ime', '$geslo', '$ime', '$priimek', '$email')";

      if(mysqli_query($zbirka, $poizvedba)){
        http_response_code(201);
        $odgovor=URL_vira($uporabnisko_ime);
        echo json_encode($odgovor);
      }
      else{
        http_response_code(500);
        if($DEBUG){
          pripravi_odgovor_napaka(mysqli_error($zbirka));
        }
      }
    }
    else{
      http_response_code(409);
      pripravi_odgovor_napaka("Uporabnik že obstaja!");
    }
  }
}

function prijava_uporabnika(){
  global $zbirka, $DEBUG;
  $podatki = json_decode(file_get_contents('php://input'), true);

  if(isset($podatki["uporabnisko_ime"], $podatki["geslo"])){
    $uporabnisko_ime = mysqli_escape_string($zbirka, $podatki["uporabnisko_ime"]);
    $geslo = mysqli_escape_string($zbirka, $podatki["geslo"]);

    if(uporabnik_obstaja($uporabnisko_ime)){
      $poizvedba = "SELECT geslo FROM uporabniki WHERE uporabnisko_ime LIKE '$uporabnisko_ime'";
      $rezultat = mysqli_query($zbirka, $poizvedba);
      $odgovor = mysqli_fetch_assoc($rezultat);
      $hashDB =  $odgovor["geslo"];

      if (password_verify($geslo, $hashDB)){
        http_response_code(200);

        session_start();
        $_SESSION['loggedin'] = true;
        $_SESSION['uporabnisko_ime'] = $uporabnisko_ime;
      }
      else {
        http_response_code(404);
        pripravi_odgovor_napaka("Napacno ime ali geslo.");
      }
    }
    else {
      http_response_code(404);
      pripravi_odgovor_napaka("Napacno ime ali geslo.");
    }
  }
}
function posodobi_uporabnika(){

  global $zbirka;
  $podatki = json_decode(file_get_contents('php://input'), true);

  session_start();
  $uporabnisko_ime = $_SESSION['uporabnisko_ime'];

  if (isset($podatki["geslo"], $podatki["ime"], $podatki["priimek"], $podatki["email"])){

    $ime = mysqli_escape_string($zbirka, $podatki["ime"]);
    $priimek = mysqli_escape_string($zbirka, $podatki["priimek"]);
    $email = mysqli_escape_string($zbirka, $podatki["email"]);

    if (mysqli_escape_string($zbirka, $podatki["geslo"]) == "*********"){
      $poizvedba="UPDATE uporabniki SET ime = '$ime', priimek = '$priimek', email ='$email' WHERE uporabnisko_ime = '$uporabnisko_ime' ";
    }
    else {
      $geslo = password_hash(mysqli_escape_string($zbirka, $podatki["geslo"]), PASSWORD_DEFAULT);
      $poizvedba="UPDATE uporabniki SET ime = '$ime', priimek = '$priimek', email ='$email', geslo='$geslo' WHERE uporabnisko_ime = '$uporabnisko_ime' ";
    }

    if(mysqli_query($zbirka, $poizvedba)){
      http_response_code(204);
    }
    else {
      http_response_code(500);
    }
  }
}
?>
