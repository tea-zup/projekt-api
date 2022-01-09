<?php

/**
 * Funkcija vzpostavi povezavo z zbirko podatkov na proceduralni način
 *
 * @return $conn objekt, ki predstavlja povezavo z izbrano podatkovno zbirko
 */
function dbConnect()
{
	$servername = "localhost";
	$username = "root";
	$password = "";
	$dbname = "prevozi";

	// Ustvarimo povezavo do podatkovne zbirke
	$conn = mysqli_connect($servername, $username, $password, $dbname);
	mysqli_set_charset($conn,"utf8");

	// Preverimo uspeh povezave
	if (mysqli_connect_errno())
	{
		printf("Povezovanje s podatkovnim strežnikom ni uspelo: %s\n", mysqli_connect_error());
		exit();
	}
	return $conn;
}

/**
 * Funkcija pripravi odgovor v obliki JSON v primeru napake
 *
 * @param $vsebina Znakovni niz, ki opisuje napako
 */
function pripravi_odgovor_napaka($vsebina)
{
	$odgovor=array(
		'status' => 0,
		'error_message'=>$vsebina
	);
	echo json_encode($odgovor);
}

function uporabnik_obstaja($uporabnisko_ime)
{
	global $zbirka;
	$uporabnisko_ime = mysqli_escape_string($zbirka, $uporabnisko_ime);

	$poizvedba="SELECT * FROM uporabniki WHERE uporabnisko_ime = '$uporabnisko_ime'";

	if(mysqli_num_rows(mysqli_query($zbirka, $poizvedba)) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}
}

function rezervacija_obstaja($id){
	global $zbirka;
	$id = mysqli_escape_string($zbirka, $id);

	$poizvedba="SELECT * FROM rezervacije WHERE id = '$id'";

	if(mysqli_num_rows(mysqli_query($zbirka, $poizvedba)) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}
}

function prevoz_obstaja($id){
	global $zbirka;
	$id = mysqli_escape_string($zbirka, $id);

	$poizvedba="SELECT * FROM prevozi WHERE id = '$id'";

	if(mysqli_num_rows(mysqli_query($zbirka, $poizvedba)) > 0)
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Funkcija pripravi URL podanega vira
 *
 * @param $vir Ime vira
 * @return $url URL podanega vira
 */
function URL_vira($vir)
{
	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
	{
		$url = "https";
	}
	else
	{
		$url = "http";
	}
	$url .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $vir;

	return $url;
}
?>
