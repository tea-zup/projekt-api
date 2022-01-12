<?php

  $DEBUG = true;
  include("orodja.php");
  $zbirka = dbConnect();

  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');	// Dovolimo dostop izven trenutne domene (CORS)
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

  if ($_SERVER["REQUEST_METHOD"] != 'POST'){ //no cookie when loggin in or registrating
    $auth_user = mysqli_escape_string($zbirka, $_SERVER["HTTP_AUTH_USER"]);
    $auth_cookie = mysqli_escape_string($zbirka, $_SERVER["HTTP_AUTH_COOKIE"]);
    if (!isset($auth_user) || !isset($auth_cookie)){
      http_response_code(403); //auth failed, cookie not set
      exit(); //ne it na switch statement
    }
    else {
      $poizvedba = "SELECT auth_cookie FROM uporabniki WHERE uporabnisko_ime = '$auth_user'";
      $rezultat = mysqli_query($zbirka, $poizvedba);
      $vrstica = mysqli_fetch_assoc($rezultat);
      if ($vrstica["auth_cookie"] != $auth_cookie){
        http_response_code(403); //auth failed, cookie is wrong
        exit(); //ne it na switch statement
      }
    }
  }

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

    if (isset($_GET["voznik"])){
      $ui = $_GET["voznik"];
    }
    else {
      $ui = $auth_user;
    }
    pridobi_uporabnika($ui);

    break;

  case 'PUT':
    posodobi_uporabnika($auth_user);
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
  $poizvedba = "SELECT uporabnisko_ime, ime, priimek, email FROM uporabniki WHERE uporabnisko_ime = '$uporabnisko_ime'";
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

        $hash = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(50))), 0, 50); // 50 characters, without /=+, plus causes a problem later
        $odg = array();
        $odg["auth_cookie"] = $hash;

        $poizvedba2="UPDATE uporabniki SET auth_cookie = '$hash' WHERE uporabnisko_ime = '$uporabnisko_ime'";

        if(mysqli_query($zbirka, $poizvedba2)){
          echo json_encode($odg);
          http_response_code(200);
        }
        else{
          http_response_code(500);
        }
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
function posodobi_uporabnika($auth_user){

  global $zbirka;
  $podatki = json_decode(file_get_contents('php://input'), true);

  if (isset($podatki["geslo"], $podatki["ime"], $podatki["priimek"], $podatki["email"])){

    $ime = mysqli_escape_string($zbirka, $podatki["ime"]);
    $priimek = mysqli_escape_string($zbirka, $podatki["priimek"]);
    $email = mysqli_escape_string($zbirka, $podatki["email"]);
    $uporabnisko_ime = mysqli_escape_string($zbirka, $auth_user);

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
