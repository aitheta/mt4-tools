<?php
namespace rosasurfer\rt\model;

use rosasurfer\db\NoSuchRecordException;
use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;

use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;
use const rosasurfer\db\orm\meta\BOOL;


/**
 * DAO for accessing {@link RosaSymbol} instances.
 */
class RosaSymbolDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_rosasymbol',
            'class'      => RosaSymbol::class,
            'properties' => [
                ['name'=>'id',                                                'type'=>INT,    'primary'=>true],                     // db:int
                ['name'=>'created',                                           'type'=>STRING,                ],                     // db:text[datetime] GMT
                ['name'=>'modified',                                          'type'=>STRING, 'version'=>true],                     // db:text[datetime] GMT

                ['name'=>'type',                                              'type'=>STRING,                ],                     // db:text forex|metals|synthetic
                ['name'=>'name',                                              'type'=>STRING,                ],                     // db:text
                ['name'=>'description',                                       'type'=>STRING,                ],                     // db:text
                ['name'=>'digits',                                            'type'=>INT,                   ],                     // db:int
                ['name'=>'autoUpdate',        'column'=>'autoupdate',         'type'=>BOOL,                  ],                     // db:int[bool]
                ['name'=>'formula',                                           'type'=>STRING,                ],                     // db:text
                ['name'=>'historyTicksStart', 'column'=>'historystart_ticks', 'type'=>STRING,                ],                     // db:text[datetime] FXT
                ['name'=>'historyTicksEnd',   'column'=>'historyend_ticks',   'type'=>STRING,                ],                     // db:text[datetime] FXT
                ['name'=>'historyM1Start',    'column'=>'historystart_m1',    'type'=>STRING,                ],                     // db:text[datetime] FXT
                ['name'=>'historyM1End',      'column'=>'historyend_m1',      'type'=>STRING,                ],                     // db:text[datetime] FXT
                ['name'=>'historyD1Start',    'column'=>'historystart_d1',    'type'=>STRING,                ],                     // db:text[datetime] FXT
                ['name'=>'historyD1End',      'column'=>'historyend_d1',      'type'=>STRING,                ],                     // db:text[datetime] FXT
            ],
            'relations' => [
                ['name'=>'dukascopySymbol', 'assoc'=>'one-to-one', 'type'=>DukascopySymbol::class, 'ref-column'=>'rosasymbol_id'],  // db:int
            ],
        ]));
    }


    /**
     * Find the {@link RosaSymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return RosaSymbol|null
     */
    public function findByName($name) {
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.getType($name));

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->find($sql);
    }


    /**
     * Get the {@link RosaSymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return RosaSymbol
     *
     * @throws NoSuchRecordException if no such instance was found
     */
    public function getByName($name) {
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.getType($name));

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->get($sql);
    }


    /**
     * Find all {@link RosaSymbol} instances with the specified auto-update status.
     *
     * @param  bool $status
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllByAutoUpdate($status) {
        if (!is_bool($status)) throw new IllegalTypeException('Illegal type of parameter $status: '.getType($status));

        $status = $this->escapeLiteral($status);
        $sql = 'select *
                   from :RosaSymbol
                   where autoupdate = '.$status.'
                   order by name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s with a Dukascopy mapping.
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllDukascopyMapped() {
        $sql = 'select r.*
                   from :RosaSymbol      r
                   join :DukascopySymbol d on r.id = d.rosasymbol_id
                   order by r.name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s with a Dukascopy mapping and the specified auto-update status.
     *
     * @param  bool $status
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllDukascopyMappedByAutoUpdate($status) {
        if (!is_bool($status)) throw new IllegalTypeException('Illegal type of parameter $status: '.getType($status));

        $status = $this->escapeLiteral($status);
        $sql = 'select r.*
                   from :RosaSymbol      r
                   join :DukascopySymbol d on r.id = d.rosasymbol_id
                   where autoupdate = '.$status.'
                   order by r.name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s of the specified type.
     *
     * @param  string $type
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllByType($type) {
        if (!is_bool($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));

        $type = $this->escapeLiteral($type);
        $sql = 'select *
                   from :RosaSymbol
                   where type = '.$type.'
                   order by name';
        return $this->findAll($sql);
    }


    /** @var string[][] - temporarily: index components */
    public static $synthetics = [
        'AUDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CADLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CHFLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'EURLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'GBPLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'JPYLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'NZDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'USDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],

        'AUDFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CADFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CHFFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'EURFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'GBPFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'JPYFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'USDFX6' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],

        'AUDFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'CADFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'CHFFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'EURFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'GBPFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'JPYFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'USDFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'NOKFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDNOK'],
        'NZDFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'SEKFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'SGDFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSGD'],
        'ZARFX7' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDZAR'],

        'EURX'   => ['EURUSD', 'GBPUSD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'USDX'   => ['EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
    ];
}
