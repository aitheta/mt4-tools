<?php
namespace rosasurfer\rost;

use rosasurfer\config\Config;
use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\rost\model\RosaSymbol;


/**
 * Rosatrader related functionality.
 */
class RT extends StaticClass {


    /**
     * Read a Rosatrader history file and return a timeseries array.
     *
     * @param  string     $fileName - file name
     * @param  RosaSymbol $symbol   - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as following:
     *
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public static function readBarFile($fileName, RosaSymbol $symbol) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
        return self::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Convert a string with Rosatrader bar data into a timeseries array.
     *
     * @param  string     $data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return array[] - array with each element describing a bar as following:
     *
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public static function readBarData($data, RosaSymbol $symbol) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

        $lenData = strLen($data); if ($lenData % Rost::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' data: '.$lenData.' (not an even Rost::BAR_SIZE)');
        $bars  = [];
        $point = $symbol->getPoint();

        for ($offset=0; $offset < $lenData; $offset += Rost::BAR_SIZE) {
            $bar = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
            $bar['open' ] *= $point;
            $bar['high' ] *= $point;
            $bar['low'  ] *= $point;
            $bar['close'] *= $point;
            $bars[] = $bar;
        }
        return $bars;
    }


    /**
     * Save a timeseries array with M1 bars of a single day to the file system.
     *
     * @param  array[]    $bars   - bar data
     * @param  RosaSymbol $symbol - instrument the data belongs to
     *
     * @return bool - success status
     */
    public static function saveM1Bars(array $bars, RosaSymbol $symbol) {
        // validate bar range
        $opentime = $bars[0]['time'];
        if ($opentime % DAY)                                   throw new RuntimeException('Invalid daily M1 data, first bar opentime: '.gmDate('D, d-M-Y H:i:s', $opentime));
        $day = $opentime - $opentime%DAY;
        if (($size=sizeOf($bars)) != DAY/MINUTES)              throw new RuntimeException('Invalid number of M1 bars for '.gmDate('D, d-M-Y', $day).': '.$size);
        if ($bars[$size-1]['time']%DAY != 23*HOURS+59*MINUTES) throw new RuntimeException('Invalid daily M1 data, last bar opentime: '.gmDate('D, d-M-Y H:i:s', $bars[$size-1]['time']));

        $point = $symbol->getPoint();

        // convert bars to binary string
        $data = null;
        foreach ($bars as $bar) {
            if ($bar['open' ] > $bar['high'] ||                 // validate bars logically
                $bar['open' ] < $bar['low' ] ||
                $bar['close'] > $bar['high'] ||
                $bar['close'] < $bar['low' ] ||
               !$bar['ticks']) throw new RuntimeException('Illegal M1 bar data for '.gmDate('D, d-M-Y H:i:s', $bar['time']).":  O=$bar[open]  H=$bar[high]  L=$bar[low]  C=$bar[close]  V=$bar[ticks]");

            $data .= pack('VVVVVV', $bar['time' ],
                         (int)round($bar['open' ]/$point),      // storing price values in points saves 40% place
                         (int)round($bar['high' ]/$point),
                         (int)round($bar['low'  ]/$point),
                         (int)round($bar['close']/$point),
                                    $bar['ticks']);
        }

        // delete existing files
        $dataDir  = Config::getDefault()['app.dir.data'];
        $dataDir .= '/history/rost/'.$symbol->getType().'/'.$symbol->getName();
        $dir      = $dataDir.'/'.gmDate('Y/m/d', $day);
        $msg      = '[Info]    '.$symbol->getName().'  deleting existing M1 file: ';
        is_file($file=$dir.'/M1.bin'    ) && true(echoPre($msg.Rost::relativePath($file))) && unlink($file);
        is_file($file=$dir.'/M1.bin.rar') && true(echoPre($msg.Rost::relativePath($file))) && unlink($file);

        // write data to new file
        $file = $dir.'/M1.bin';
        mkDirWritable(dirName($file));
        $tmpFile = tempNam(dirName($file), baseName($file));
        $hFile   = fOpen($tmpFile, 'wb');
        fWrite($hFile, $data);
        fClose($hFile);
        rename($tmpFile, $file);                                // this way an existing file can't be corrupt

        return true;
    }
}
