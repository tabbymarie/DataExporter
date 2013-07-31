<?php

namespace spec\EE\DataExporterBundle\Service;

use PhpSpec\ObjectBehavior;

/**
 * Class DataExporterSpec
 */
class DataExporterSpec extends ObjectBehavior
{

    public function it_should_be_initializable()
    {
        $this->shouldHaveType('EE\DataExporterBundle\Service\DataExporter');
    }

    public function it_throws_exception_if_there_setOptions_is_no_called()
    {
        $this
            ->shouldThrow('RuntimeException')
            ->duringSetColumns(array());
    }

    public function it_csv_export_test()
    {
        $this->setOptions('csv', array('fileName' => 'file', 'separator' => ';'));
        $this->setColumns(array('[col1]', '[col2]', '[col3]'));
        $this->setData(
            array(
                array('col1' => '1a', 'col2' => '1b', 'col3' => '1c'),
                array('col1' => '2a', 'col2' => '2b'),
            )
        );

        $this->render()->shouldHaveType('Symfony\Component\HttpFoundation\Response');
        $this->render()->getContent()->shouldBe("[col1];[col2];[col3]\n1a;1b;1c\n2a;2b;");
    }

    public function it_test_csv_export_from_object()
    {
        $testObject = new TestObject();

        $this->setOptions('csv', array('fileName' => 'file', 'separator' => ';'));
        $this->setColumns(array('col1' => 'Label1', 'col2' => 'Label2'));
        $this->setData(array($testObject));

        $this->render()->shouldHaveType('Symfony\Component\HttpFoundation\Response');
        $this->render()->getContent()->shouldReturn("Label1;Label2\n1a;1b");
    }

    public function it_test_xls_export_from_object()
    {
        $testObject = new TestObject();

        $this->setOptions('xls', array('fileName' => 'file'));
        $this->setColumns(array('col1' => 'Label1', 'col2' => 'Label2', 'col3.col1' => 'From object two'));
        $this->setData(array($testObject));

        $result = '<!DOCTYPE ><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="ProgId" content="Excel.Sheet"><meta name="Generator" content="https://github.com/EE/DataExporter"></head><body><table><tr><td>Label1</td><td>Label2</td><td>From object two</td></tr><tr><td>1a</td><td>1b</td><td>Object two</td></tr></table></body></html>';

        $this->render()->shouldReturnAnInstanceOf('Symfony\Component\HttpFoundation\Response');
        $this->render()->getContent()->shouldReturn($result);
    }
}

class TestObject
{
    private $col1;
    private $col2;
    private $col3;

    public function __construct()
    {
        $this->col1 = '1a';
        $this->col2 = '1b';
        $this->col3 = new TestObject2;
    }

    public function setCol2($col2)
    {
        $this->col2 = $col2;
    }

    public function getCol2()
    {
        return $this->col2;
    }

    public function setCol1($col1)
    {
        $this->col1 = $col1;
    }

    public function getCol1()
    {
        return $this->col1;
    }

    public function setCol3($col3)
    {
        $this->col3 = $col3;
    }

    public function getCol3()
    {
        return $this->col3;
    }
}

class TestObject2
{
    private $col1;

    public function __construct()
    {
        $this->col1 = 'Object two';
    }

    public function setCol1($col1)
    {
        $this->col1 = $col1;
    }

    public function getCol1()
    {
        return $this->col1;
    }
}
