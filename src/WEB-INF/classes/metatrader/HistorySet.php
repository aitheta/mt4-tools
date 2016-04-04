<?php
/**
 * Ein HistorySet zur Verwaltung der MetaTrader-History eines Instruments. Die Formate der einzelnen Dateien
 * eines HistorySets k�nnen gemischt sein.
 */
class HistorySet extends Object {

   protected /*string*/ $symbol;
   protected /*int   */ $digits;
   protected /*string*/ $serverName;                  // einfacher Servername
   protected /*string*/ $serverDirectory;             // vollst�ndiger Verzeichnisname
   protected /*bool  */ $disposed = false;            // ob die Resourcen dieses Sets freigegeben sind

   // Getter
   public function getSymbol()          { return       $this->symbol;          }
   public function getDigits()          { return       $this->digits;          }
   public function getServerName()      { return       $this->serverName;      }
   public function getServerDirectory() { return       $this->serverDirectory; }
   public function isDisposed()         { return (bool)$this->disposed;        }

   protected /*HistoryFile[]*/ $historyFiles = array(PERIOD_M1  => null,
                                                     PERIOD_M5  => null,
                                                     PERIOD_M15 => null,
                                                     PERIOD_M30 => null,
                                                     PERIOD_H1  => null,
                                                     PERIOD_H4  => null,
                                                     PERIOD_D1  => null,
                                                     PERIOD_W1  => null,
                                                     PERIOD_MN1 => null);

   private static /*HistorySet[]*/ $instances = array(); // alle Instanzen dieser Klasse


   /**
    * �berladener Constructor.
    *
    * Signaturen:
    * -----------
    * new HistorySet(string $symbol, int $digits, int $format, string $serverDirectory)
    * new HistorySet(HistoryFile $file)
    */
   private function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null) {
      $argc = func_num_args();
      if      ($argc == 4) $this->__construct_1($arg1, $arg2, $arg3, $arg4);
      else if ($argc == 1) $this->__construct_2($arg1);
      else throw new plInvalidArgumentException('Invalid number of arguments: '.$argc);
   }


   /**
    * Constructor 1
    *
    * Erzeugt eine neue Instanz und legt alle Historydateien neu an. Vorhandene Daten werden gel�scht. Mehrfachaufrufe
    * dieser Funktion f�r dasselbe Symbol desselben Servers geben jeweils eine neue Instanz zur�ck, weitere existierende
    * Instanzen werden als ung�ltig markiert.
    *
    * @param  string $symbol          - Symbol der HistorySet-Daten
    * @param  int    $digits          - Digits der Datenreihe
    * @param  int    $format          - Speicherformat der Datenreihen:
    *                                   � 400: MetaTrader <= Build 509
    *                                   � 401: MetaTrader  > Build 509
    * @param  string $serverDirectory - Serververzeichnis der Historydateien des Sets
    */
   private function __construct_1($symbol, $digits, $format, $serverDirectory) {
      if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))                         throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new plInvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
      if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.getType($digits));
      if ($digits < 0)                              throw new plInvalidArgumentException('Invalid parameter $digits: '.$digits);
      if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))                throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      $this->symbol          = $symbol;
      $this->digits          = $digits;
      $this->serverDirectory = realPath($serverDirectory);
      $this->serverName      = baseName($this->serverDirectory);

      // offene Sets durchsuchen und Sets desselben Symbols freigeben
      $symbolUpper = strToUpper($this->symbol);
      foreach (self::$instances as $instance) {
         if (!$instance->isDisposed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
            $instance->dispose();
      }

      // alle HistoryFiles zur�cksetzen
      foreach ($this->historyFiles as $timeframe => &$file) {
         $file = new HistoryFile($symbol, $timeframe, $digits, $format, $serverDirectory);
      } unset($file);

      // Instanz speichern
      self::$instances[] = $this;
   }


   /**
    * Constructor 2
    *
    * Erzeugt eine neue Instanz. Vorhandene Daten werden nicht gel�scht.
    *
    * @param  HistoryFile $file - existierende History-Datei
    */
   private function __construct_2(HistoryFile $file) {
      $this->symbol          =          $file->getSymbol();
      $this->digits          =          $file->getDigits();
      $this->serverName      =          $file->getServerName();
      $this->serverDirectory = realPath($file->getServerDirectory());

      $this->historyFiles[$file->getTimeframe()] = $file;

      $symbolUpper = strToUpper($this->symbol);
      foreach (self::$instances as $instance) {
         if (!$instance->isDisposed() && $symbolUpper==strToUpper($instance->getSymbol()) && $this->serverDirectory==$instance->getServerDirectory())
            throw plRuntimeException('Multiple undisposed HistorySets for "'.$this->serverName.'::'.$this->symbol.'"');
      }

      // alle �brigen existierenden HistoryFiles �ffnen und validieren (nicht existierende Dateien werden bei Bedarf erstellt)
      foreach ($this->historyFiles as $timeframe => &$file) {
         if (!$file) {
            $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';
            if (is_file($fileName)) {
               try {
                  $file = new HistoryFile($fileName);
               }
               catch (MetaTraderException $ex) {
                  if (strStartsWith($ex->getMessage(), 'filesize.insufficient')) {
                     Logger::warn($ex->getMessage(), __CLASS__);           // eine zu kurze Datei wird sp�ter �berschrieben
                     continue;
                  }
                  throw $ex;
               }
               if (!strCompareI($file->getSymbol(), $this->getSymbol())) throw new plRuntimeException('Symbol mis-match in "'.$fileName.'": '.$file->getSymbol().' instead of '.$this->getSymbol());
               if ($file->getDigits() != $this->getDigits())             throw new plRuntimeException('Digits mis-match in "'.$fileName.'": '.$file->getDigits().' instead of '.$this->getDigits());
            }
         }
      } unset($file);

      self::$instances[] = $this;
   }


   /**
    * Destructor
    *
    * Sorgt bei Zerst�rung der Instanz daf�r, da� alle gehaltenen Resourcen freigegeben werden.
    */
   public function __destruct() {
      // Ein Destructor darf w�hrend des Shutdowns keine Exception werfen.
      try {
         $this->dispose();
      }
      catch (Exception $ex) {
         Logger::handleException($ex, $inShutdownOnly=true);
         throw $ex;
      }
   }


   /**
    * Gibt alle Resourcen dieser Instanz frei. Nach dem Aufruf kann die Instanz nicht mehr verwendet werden.
    *
    * @return bool - Erfolgsstatus; FALSE, wenn die Instanz bereits disposed war
    */
   public function dispose() {
      if ($this->isDisposed())
         return false;

      foreach ($this->historyFiles as $file) {
         $file && $file->dispose();
      }
      return $this->disposed=true;
   }


   /**
    * �ffentlicher Zugriff auf Constructor 1
    */
   public static function create($symbol, $digits, $format, $serverDirectory) {
      return new self($symbol, $digits, $format, $serverDirectory);
   }


   /**
    * �ffentlicher Zugriff auf Constructor 2
    *
    * Gibt eine Instanz f�r bereits vorhandene Historydateien zur�ck. Vorhandene Daten werden nicht gel�scht. Es mu� mindestens
    * ein HistoryFile des Symbols existieren. Nicht existierende HistoryFiles werden beim Speichern der ersten hinzugef�gten
    * Daten angelegt. Mehrfachaufrufe dieser Funktion f�r dasselbe Symbol desselben Servers geben dieselbe Instanz zur�ck.
    *
    * @param  string $symbol          - Symbol der Historydateien
    * @param  string $serverDirectory - Serververzeichnis, in dem die Historydateien gespeichert sind
    *
    * @return HistorySet - Instanz oder NULL, wenn keine entsprechenden Historydateien gefunden wurden oder die
    *                      gefundenen Dateien korrupt sind.
    */
   public static function get($symbol, $serverDirectory) {
      if (!is_string($symbol))          throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))             throw new plInvalidArgumentException('Invalid parameter $symbol: ""');
      if (!is_string($serverDirectory)) throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
      if (!is_dir($serverDirectory))    throw new plInvalidArgumentException('Directory "'.$serverDirectory.'" not found');

      // existierende Instanzen durchsuchen und bei Erfolg die entsprechende Instanz zur�ckgeben
      $symbolUpper     = strToUpper($symbol);
      $serverDirectory = realPath($serverDirectory);
      foreach (self::$instances as $instance) {
         if (!$instance->isDisposed() && $symbolUpper==strToUpper($instance->getSymbol()) && $serverDirectory==$instance->getServerDirectory())
            return $instance;
      }

      // das erste existierende HistoryFile an den Constructor �bergeben, das Set liest die weiteren dann selbst ein
      $set = $file = null;
      foreach (MT4::$timeframes as $timeframe) {
         $fileName = $serverDirectory.'/'.$symbol.$timeframe.'.hst';
         if (is_file($fileName)) {
            try {
               $file = new HistoryFile($fileName);
            }
            catch (MetaTraderException $ex) {
               Logger::warn($ex->getMessage(), __CLASS__);
               continue;
            }
            $set = new self($file);
            break;
         }
      }
      return $set;
   }


   /**
    * Gibt das HistoryFile des angegebenen Timeframes zur�ck. Existiert es nicht, wird eine neue Instanz ereugt.
    *
    * @param  int $timeframe
    *
    * @return HistoryFile
    */
   private function getFile($timeframe) {
      if (!isSet($this->historyFiles[$timeframe])) {
         $fileName = $this->serverDirectory.'/'.$this->symbol.$timeframe.'.hst';
         $file     = null;
         if (!$file)
            $file = new HistoryFile($this->symbol, $timeframe, $this->digits, $format=400, $this->serverDirectory);

         $this->historyFiles[$timeframe] = $file;
      }

      return $this->historyFiles[$timeframe];
   }


   /**
    * Gibt den letzten f�r alle HistoryFiles des Sets g�ltigen Synchronisationszeitpunkt zur�ck.
    * Dies ist der �lteste Synchronisationszeitpunkt der einzelnen HistoryFiles.
    *
    * @return int - Timestamp (in jeweiliger Serverzeit)
    *               0, wenn mindestens eines der HistoryFiles noch gar nicht synchronisiert wurde
    */
   public function getLastSyncTime() {
      $allTime = null;
      foreach ($this->historyFiles as $timeframe => $file) {
         $time = $file ? $file->getLastSyncTime() : 0;   // existiert die Datei nicht, wurde sie auch noch nicht synchronisiert

         is_null($allTime) && $allTime=$time;
         if ($time != $allTime)
            return 0;                                    // vorerst mu� die LastSyncTime aller Dateien �bereinstimmen
      }
      return $allTime;
   }


   /**
    * F�gt dem gesamten Set weitere M1-Bardaten hinzu.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   public function addM1Bars(array $bars) {
      if ($this->disposed) throw new IllegalStateException('cannot modify a disposed '.__CLASS__.' instance');

      foreach ($this->historyFiles as $timeframe => $file) {
         !$file && $file=$this->getFile($timeframe);
         $file->addM1Bars($bars);
      }
   }


   /**
    * Synchronisiert das Set anhand der �bergebenen M1-Bardaten.
    *
    * @param  MYFX_BAR[] $bars - Bardaten der Periode M1
    */
   public function update(array $bars) {
      if ($this->disposed) throw new IllegalStateException('cannot modify a disposed '.__CLASS__.' instance');
      throw new UnimplementedFeatureException(__METHOD__);
   }


   /**
    * Nur zum Debuggen
    */
   public function showBuffer() {
      echoPre(NL);
      foreach ($this->historyFile as $timeframe => $file) {
         if ($file) {
            $bars = $file->barBuffer;
            $size = sizeOf($bars);
            $firstBar = $lastBar = null;
            if ($size) {
               if (isSet($bars[0]['time']) && $bars[$size-1]['time']) {
                  $firstBar = '  from='.gmDate('d-M-Y H:i', $bars[0      ]['time']);
                  $lastBar  = '  to='  .gmDate('d-M-Y H:i', $bars[$size-1]['time']);
               }
               else {
                  $firstBar = $lastBar = '  invalid';
                  echoPre($bars);
               }
            }
            echoPre(get_class($file).'['. str_pad(MyFX::timeframeDescription($file->getTimeframe()), 3, ' ', STR_PAD_RIGHT).'] => '.str_pad($size, 5, ' ', STR_PAD_LEFT).' bar'.($size==1?' ':'s').$firstBar.($size>1? $lastBar:''));
         }
         else {
            echoPre('HistoryFile['. str_pad(MyFX::timeframeDescription($timeframe), 3, ' ', STR_PAD_RIGHT).'] => mull');
         }
      }
      echoPre(NL);
   }
}
