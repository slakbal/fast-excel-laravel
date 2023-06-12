<?php


declare(strict_types=1);

namespace avadim\FastExcelLaravel;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use avadim\FastExcelReader\Excel as ExcelReader;
use \Illuminate\Filesystem\Filesystem as File;

//final class FastExcelLaravelTest extends TestCase
final class FastExcelLaravelTest extends \Orchestra\Testbench\TestCase
{
    protected ?ExcelReader $excelReader = null;
    protected array $cells = [];
    protected string $testStorage;


    protected function setUp(): void
    {
        parent::setUp();
        $this->testStorage = __DIR__ . '/test_storage';

        app()->useStoragePath($this->testStorage);
    }

    protected function getValue($cell)
    {
        preg_match('/^(\w+)(\d+)$/', strtoupper($cell), $m);

        return $this->cells[$m[2]][$m[1]]['v'] ?? null;
    }

    protected function getValues(...$cells): array
    {
        $result = [];
        foreach ($cells as $cell) {
            $result[] = $this->getValue($cell);
        }

        return $result;
    }

    protected function getStyle($cell, $flat = false)
    {
        preg_match('/^(\w+)(\d+)$/', strtoupper($cell), $m);
        $styleIdx = $this->cells[$m[2]][$m[1]]['s'] ?? null;
        if ($styleIdx !== null) {
            $style = $this->excelReader->getCompleteStyleByIdx($styleIdx);
            if ($flat) {
                $result = [];
                foreach ($style as $key => $val) {
                    $result = array_merge($result, $val);
                }
            }
            else {
                $result = $style;
            }

            return $result;
        }

        return [];
    }


    protected function getDataArray(): array
    {
        $id = 0;
        return [
            ['id' => $id++, 'integer' => 4573, 'date' => '1900-02-14', 'name' => 'James Bond'],
            ['id' => $id++, 'integer' => 982630, 'date' => '2179-08-12', 'name' => 'Ellen Louise Ripley'],
            ['id' => $id++, 'integer' => 7239, 'date' => '1753-01-31', 'name' => 'Captain Jack Sparrow'],
        ];
    }

    protected function getDataCollectionStd(): Collection
    {
        $data = $this->getDataArray();
        $result = [];
        foreach ($data as $row) {
            $result[] = (object)$row;
        }

        return collect($result);
    }

    protected function read($testFileName)
    {
        $this->assertTrue(file_exists($testFileName));

        $this->excelReader = ExcelReader::open($testFileName);
        $this->cells = $this->excelReader->readRows(false, null, true);
    }


    protected function startTest($testFileName, $sheets = []): ExcelWriter
    {
        if (file_exists($testFileName)) {
            unlink($testFileName);
        }
        elseif (file_exists(storage_path($testFileName))) {
            unlink(storage_path($testFileName));
        }

        return Excel::create($sheets);
    }

    protected function endTest($testFileName)
    {
        $this->excelReader = null;
        $this->cells = [];

        if (file_exists($testFileName)) {
            unlink($testFileName);
        }
        elseif (file_exists(storage_path($testFileName))) {
            unlink(storage_path($testFileName));
        }
    }

    public function testArray()
    {
        $testFileName = __DIR__ . '/test1.xlsx';
        $excel = $this->startTest($testFileName);

        /** @var SheetWriter $sheet */
        $sheet = $excel->getSheet();

        $data = $this->getDataArray();
        $sheet->writeData($data);
        $excel->save($testFileName);

        $this->read($testFileName);

        $this->assertEquals(array_values($data[0]), $this->getValues('A1', 'B1', 'C1', 'D1'));

        $this->endTest($testFileName);
    }

    public function testArrayWithHeaders()
    {
        $testFileName = __DIR__ . '/test2.xlsx';
        $excel = $this->startTest($testFileName);

        /** @var SheetWriter $sheet */
        $sheet = $excel->getSheet();

        $data = $this->getDataArray();
        $sheet->withHeaders()->writeData($data);
        $excel->save($testFileName);

        $this->read($testFileName);
        $row = $data[1];

        $this->assertEquals(array_keys($row), $this->getValues('A1', 'B1', 'C1', 'D1'));
        $this->assertEquals(array_values($row), $this->getValues('A3', 'B3', 'C3', 'D3'));

        $this->endTest($testFileName);
    }

    public function testCollection()
    {
        $testFileName = __DIR__ . '/test3.xlsx';
        $excel = $this->startTest($testFileName);

        /** @var SheetWriter $sheet */
        $sheet = $excel->getSheet();

        $data = $this->getDataArray();
        $sheet->writeData(collect($this->getDataCollectionStd()));
        $excel->save($testFileName);

        $this->read($testFileName);

        $this->assertEquals(array_values($data[0]), $this->getValues('A1', 'B1', 'C1', 'D1'));

        $this->endTest($testFileName);
    }

    public function testCollectionWithHeaders()
    {
        $testFileName = 'test4.xlsx';
        $excel = $this->startTest($testFileName);

        /** @var SheetWriter $sheet */
        $sheet = $excel->getSheet();

        $sheet->withHeaders(['date', 'name'])
            ->applyFontStyleBold()
            ->applyBorder('thin')
            ->writeData(collect($this->getDataCollectionStd()));
        $excel->saveTo($testFileName);

        $this->read(storage_path($testFileName));

        $this->assertEquals(['1753-01-31', 'Captain Jack Sparrow', null, null], $this->getValues('A4', 'B4', 'C4', 'D4'));

        $this->endTest($testFileName);
    }

    public function testMultipleSheets()
    {
        $testFileName = 'test5.xlsx';
        $excel = $this->startTest($testFileName);

        $sheet = $excel->makeSheet('Collection');
        $collection = collect([
            [ 'id' => 1, 'site' => 'google.com' ],
            [ 'id' => 2, 'site.com' => 'youtube.com' ],
        ]);
        $sheet->writeData($collection);

        $sheet = $excel->makeSheet('Array');
        $array = [
            [ 'id' => 1, 'name' => 'Helen' ],
            [ 'id' => 2, 'name' => 'Peter' ],
        ];
        $sheet->writeData($array);

        $sheet = $excel->makeSheet('Callback');
        $sheet->writeData(function () {
            for ($i = 1; $i <= 3; $i++) {
                yield [$i, $i * 2, $i * 3];
            }
        });

        $excel->saveTo($testFileName);
        $file = storage_path($testFileName);

        $this->assertTrue(file_exists($file));

        $this->excelReader = ExcelReader::open($file);
        $this->excelReader->selectSheet('Collection');
        $this->cells = $this->excelReader->readRows(false, null, true);
        $this->assertEquals('youtube.com', $this->getValue('b2'));

        $this->excelReader->selectSheet('Array');
        $this->cells = $this->excelReader->readRows(false, null, true);
        $this->assertEquals('Peter', $this->getValue('b2'));

        $this->excelReader->selectSheet('Callback');
        $this->cells = $this->excelReader->readRows(false, null, true);
        $this->assertEquals(9, $this->getValue('C3'));

        $this->endTest($testFileName);
    }

    public function testAdvanced()
    {
        $testFileName = 'test6.xlsx';
        $excel = $this->startTest($testFileName);

        /** @var SheetWriter $sheet */
        $sheet = $excel->getSheet();

        $sheet->setColWidth('B', 12);
        $sheet->setColOptions('c', ['width' => 12, 'text-align' => 'center']);
        $sheet->setColWidth('d', 'auto');

        $title = 'This is demo of avadim/fast-excel-laravel';
        $area = $sheet->beginArea();
        $area->setValue('A2:D2', $title)
            ->applyFontSize(14)
            ->applyFontStyleBold()
            ->applyTextCenter();

        $area
            ->setValue('a4:a5', '#')
            ->setValue('b4:b5', 'Number')
            ->setValue('c4:d4', 'Movie Character')
            ->setValue('c5', 'Birthday')
            ->setValue('d5', 'Name')
        ;
        $area->withRange('a4:d5')
            ->applyBgColor('#ccc')
            ->applyFontStyleBold()
            ->applyOuterBorder('thin')
            ->applyInnerBorder('thick')
            ->applyTextCenter();
        $sheet->writeAreas();

        $sheet->writeData(collect($this->getDataCollectionStd()));
        $excel->saveTo($testFileName);

        $this->read(storage_path($testFileName));

        $this->assertEquals([982630, '2179-08-12', 'Ellen Louise Ripley', null], $this->getValues('B7', 'C7', 'D7', 'e7'));

        $this->endTest($testFileName);
    }

}