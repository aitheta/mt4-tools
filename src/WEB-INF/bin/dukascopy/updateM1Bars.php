#!/usr/bin/php -Cq
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten im aktuellen Verzeichnis.
 *
 */
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));       // Konfiguration im WEB-INF-Verzeichnis einbinden

define('APPLICATION_NAME', 'myfx.pewasoft');                         // APPLICATION_ROOT: {project}/src
define('APPLICATION_ROOT', join(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirName(__FILE__)), 0, -3)));

// PHPLib, Definitionen und Klassen einbinden
require(APPLICATION_ROOT.'/../../php-lib/src/phpLib.php');
include(APPLICATION_ROOT.'/WEB-INF/include/defines.php');
include(APPLICATION_ROOT.'/WEB-INF/classes/classes.php');


// Beginn der Daten der einzelnen Pairs in den einzelnen Perioden (Zeitzone: GMT)
$data = array('M1'  => array('AUDJPY' => strToTime('2003-11-30 00:00:00 GMT'),
                             'AUDNZD' => strToTime('2009-01-08 00:00:00 GMT'),
                             'AUDUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                             'CADJPY' => strToTime('2005-04-17 00:00:00 GMT'),
                             'CHFJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                             'EURAUD' => strToTime('2007-04-02 00:00:00 GMT'),
                             'EURCAD' => strToTime('2008-09-24 00:00:00 GMT'),
                             'EURCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                             'EURGBP' => strToTime('2003-08-08 00:00:00 GMT'),
                             'EURJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                             'EURNOK' => strToTime('2007-04-02 00:00:00 GMT'),
                             'EURSEK' => strToTime('2004-11-16 00:00:00 GMT'),
                             'EURUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                             'GBPCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                             'GBPJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                             'GBPUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                             'NZDUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                             'USDCAD' => strToTime('2003-08-08 00:00:00 GMT'),
                             'USDCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                             'USDJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                             'USDNOK' => strToTime('2003-08-08 00:00:00 GMT'),
                             'USDSEK' => strToTime('2003-08-08 00:00:00 GMT'),
              ));

$today  = time();
$today -= $today % DAY;


foreach ($data['M1'] as $symbol => $start) {
   $start -= $start % DAY;

   for ($time=$start; $time < $today; $time+=DAY) {         // Daten des heutigen Tages k�nnen noch nicht existieren
      date_default_timezone_set('America/New_York');
      $startDoW = date('w', $time + 7*HOURS);
      $endDoW   = date('w', $time + 7*HOURS+DAY);
      if ($startDoW==SATURDAY || $endDoW==SUNDAY)           // Wochenenden �berspringen, Sessionbeginn/-ende ist America/New_York+0700
         continue;

      // URLs zusammenstellen und laden
      date_default_timezone_set('GMT');
      $year  = date('Y', $time);
      $month = subStr(date('n', $time)+99, 1);              // Januar = 00
      $day   = date('d', $time);
      $path  = "$symbol/$year/$month/$day";
      $file  = 'BID_candles_min_1.bin';
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";
      download($url, $path, $file);

      $file  = 'ASK_candles_min_1.bin';
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";
      download($url, $path, $file);
   }
}


/**
 * L�dt eine URL und speichert die Antwort unter dem angegebenen Dateinamen.
 *
 * @param string $url      - URL
 * @param string $path     - Verzeichnis, in dem die Datei gespeichert werden soll
 * @param string $filename - Dateiname
 */
function download($url, $path, $filename) {
   if (!is_string($url))      throw new IllegalTypeException('Illegal type of argument $url: '.getType($url));
   if (!is_string($path))     throw new IllegalTypeException('Illegal type of argument $path: '.getType($path));
   if (!is_string($filename)) throw new IllegalTypeException('Illegal type of argument $filename: '.getType($filename));

   // Downloadverzeichnis bestimmen
   static $downloadDirectory = null;
   if ($downloadDirectory === null)
      $downloadDirectory = MyFXHelper ::getAbsoluteConfigPath('history.dukascopy');

   // bereits heruntergeladene Dateien �berspringen
   $path = $downloadDirectory.PATH_SEPARATOR.$path;
   $file = $path.PATH_SEPARATOR.$file;
   if (is_file($file) || is_file($file.'.404')) {       // Datei, f�r die 404 zur�ckgegeben wurde
      echoPre("[Info]: Skipping url \"$url\", local file already exists.");
      return;
   }

   // HTTP-Request abschicken und auswerten
   $request  = HttpRequest ::create()->setUrl($url);
   $response = CurlHttpClient ::create()->send($request);
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new plRuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

   // ggf. Zielverzeichnis anlegen
   if (is_file($path))                              throw new plInvalidArgumentException('Cannot write to directory "'.$path.'" (is file)');
   if (!is_dir($path) && !mkDir($path, 0700, true)) throw new plInvalidArgumentException('Cannot create directory "'.$path.'"');
   if (!is_writable($path))                         throw new plInvalidArgumentException('Cannot write to directory "'.$path.'"');

   // Datei speichern ...
   if ($status == 200) {
      echoPre("[Ok]: $url");
      $hFile = fOpen($file, 'xb');
      fWrite($hFile, $response->getContent());
      fClose($hFile);
   }
   else {   // ... oder 404-Status mit leerer .404-Datei merken
      echoPre("[Info]: $status - File not found: \"$url\"");
      fClose(fOpen($file.'.404', 'x'));
   }
}
?>
