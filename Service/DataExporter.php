<?php

namespace AntQa\Bundle\DataExporterBundle\Service;

use Knp\Bundle\SnappyBundle\Snappy\LoggableGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Templating\EngineInterface;

/**
 * DataExporter
 *
 * @author  Piotr Antosik <mail@piotrantosik.com>
 * @version Release: 0.5
 *
 * TODO: /^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/ jako kluczi  przechodzi 2014-02-14
 */
class DataExporter
{
    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $hooks = array();

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $registredBundles;

    /**
     * @var LoggableGenerator|null
     */
    protected $knpSnappyPdf;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @param array                  $registredBundles
     * @param EngineInterface        $templating
     * @param null $knpSnappyPdf
     */
    public function __construct($registredBundles, EngineInterface $templating, LoggableGenerator $knpSnappyPdf = null)
    {
        $this->registredBundles = $registredBundles;
        $this->templating = $templating;
        $this->knpSnappyPdf = $knpSnappyPdf;
    }

    /**
     * @param array $options
     *
     * @throws \Exception
     */
    public function setOptions($options = array())
    {
        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);
        $this->options = $resolver->resolve($options);

        switch ($this->getFormat()) {
            case 'csv':
                $this->data = array();
                break;
            case 'xls':
                $this->openXLS();
                break;
            case 'html':
                $this->openHTML();
                break;
            case 'xml':
                $this->openXML();
                break;
            case 'pdf':
                if (false === array_key_exists('KnpSnappyBundle', $this->registredBundles)) {
                    throw new \Exception('KnpSnappyBundle must be installed');
                }

                break;
        }

        if (true === $this->getSkipHeader() && $this->getFormat() !== 'csv') {
            throw new \Exception('Only CSV support skip_header option!');
        }
    }

    public function reload()
    {
        $this->columns = null;
        $this->data = null;
    }

    protected function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'format' => 'csv',
                'charset'=> 'utf-8',
                'fileName' => function (Options $options) {
                        $date = new \DateTime();

                        return sprintf('Data export %s.%s', $date->format('Y-m-d H:i:s'), $options['format']);
                    },
                'memory' => false,
                'skipHeader' => false,
                'allowNull' => false,
                'nullReplace' => false,
                'separator' => function (Options $options) {
                        if ('csv' === $options['format']) {
                            return ',';
                        }

                        return null;
                    },
                'escape' => function (Options $options) {
                        if ('csv' === $options['format']) {
                            return '\\';
                        }

                        return null;
                    },
                'onlyContent' => function(Options $options) {
                        if ('html' === $options['format']) {
                            return false;
                        }

                        return null;
                    },
                'template' => function (Options $options) {
                        if ('pdf' === $options['format'] || 'render' === $options['format']) {
                            return 'AntQaDataExporterBundle::base.pdf.twig';
                        }

                        return null;
                    },
                'template_vars' => array(),
                'pdfOptions' => function (Options $options) {
                        if ('pdf' === $options['format']) {
                            return array(
                                'orientation' => 'Landscape'
                            );
                        }

                        return null;
                    }
            ));
        $resolver->setAllowedValues(array(
                'format' => array('csv', 'xls', 'html', 'xml', 'json', 'pdf', 'listData', 'render')
            ));
        $resolver->setAllowedTypes(array(
                'charset' => 'string',
                'fileName' => 'string',
                'memory' => array('null', 'bool'),
                'skipHeader' => array('null', 'bool'),
                'separator' => array('null', 'string'),
                'escape' => array('null', 'string'),
                'allowNull' => 'bool',
                'nullReplace' => 'bool',
                'template' => array('null', 'string'),
                'template_vars' => 'array',
                'pdfOptions' => array('null', 'array'),
                'onlyContent' => array('null', 'bool')
            ));
    }

    /**
     * @return string
     */
    private function getFormat()
    {
        return $this->options['format'];
    }

    /**
     * @return Boolean|null
     */
    private function getInMemory()
    {
        return $this->options['memory'];
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return $this->options['fileName'];
    }

    /**
     * @return Boolean|null
     */
    private function getSkipHeader()
    {
        return $this->options['skipHeader'];
    }

    /**
     * @return Boolean|null
     */
    private function getOnlyContent()
    {
        return $this->options['onlyContent'];
    }

    /**
     * @return string|null
     */
    private function getTemplate()
    {
        return $this->options['template'];
    }

    /**
     * @return array
     */
    private function getTemplateVars()
    {
        return $this->options['template_vars'];
    }

    /**
     * @return array|null
     */
    private function getPdfOptions()
    {
        return $this->options['pdfOptions'];
    }

    /**
     * @return string
     */
    private function getCharset()
    {
        return $this->options['charset'];
    }

    /**
     * @return string
     */
    private function getSeparator()
    {
        return $this->options['separator'];
    }

    /**
     * @return string
     */
    private function getEscape()
    {
        return $this->options['escape'];
    }

    private function getAllowNull()
    {
        return $this->options['allowNull'];
    }

    private function getNullReplace()
    {
        return $this->options['nullReplace'];
    }

    /**
     * @return $this
     */
    private function openXML()
    {
        $this->data = '<?xml version="1.0" encoding="' . $this->getCharset() . '"?><table>';

        return $this;
    }

    /**
     * @return $this
     */
    private function closeXML()
    {
        $this->data .= "</table>";

        return $this;
    }

    /**
     * @return $this
     */
    private function openXLS()
    {
        $this->data = sprintf("<!DOCTYPE ><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=%s\" /><meta name=\"ProgId\" content=\"Excel.Sheet\"><meta name=\"Generator\" content=\"https://github.com/piotrantosik/DataExporter\"></head><body><table>", $this->getCharset());

        return $this;
    }

    /**
     * @return $this
     */
    private function closeXLS()
    {
        $this->data .= "</table></body></html>";

        return $this;
    }

    /**
     * @return $this
     */
    private function openHTML()
    {
        if (!$this->getOnlyContent()) {
            $this->data = "<!DOCTYPE ><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . $this->getCharset() . "\" /><meta name=\"Generator\" content=\"https://github.com/piotrantosik/DataExporter\"></head><body><table>";
        } else {
            $this->data = '<table>';
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function closeHTML()
    {
        if (!$this->getOnlyContent()) {
            $this->data .= "</table></body></html>";
        } else {
            $this->data .= "</table>";
        }

        return $this;
    }

    /**
     * @param string $data
     * @param string $separator
     * @param string $escape
     * @param string $column
     * @param array  $hooks
     * @param string $format
     *
     * @return string
     */
    public static function escape($data, $separator, $escape, $column, $hooks, $format)
    {
        //check for hook
        if (array_key_exists($column, $hooks)) {
            //check for closure
            if (false === is_array($hooks[$column])) {
                $data = $hooks[$column]($data);
            } else {
                $refl = new \ReflectionMethod($hooks[$column][0], $hooks[$column][1]);
                if (is_object($hooks[$column][0])) {
                    $obj = $hooks[$column][0];
                    $data = $obj->$hooks[$column][1]($data);
                } elseif ($refl->isStatic()) {
                    $data = $hooks[$column][0]::$hooks[$column][1]($data);
                } else {
                    $obj = new $hooks[$column][0];
                    $data = $obj->$hooks[$column][1]($data);
                }
            }
        }

        //replace new line character
        $data = preg_replace("/\r\n|\r|\n/", ' ', $data);

        $data = mb_ereg_replace(
            sprintf('%s', $separator),
            sprintf('%s', $escape),
            $data
        );

        if ('xml' === $format) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                $data = htmlspecialchars($data, ENT_XML1);
            } else {
                $data = htmlspecialchars($data);
            }
        }
        //strip html tags
        if (in_array($format, array('csv', 'xls'))) {
            $data = strip_tags($data);
        }

        return $data;
    }

    /**
     * @param array|\Closure  $function
     * @param string          $column
     *
     * @return $this|bool
     * @throws \BadFunctionCallException
     * @throws \InvalidArgumentException
     * @throws \LengthException
     */
    public function addHook($function, $column)
    {
        //check for closure
        if (false === is_array($function)) {
            $functionReflected = new \ReflectionFunction($function);
            if ($functionReflected->isClosure()) {
                $this->hooks[$column] = $function;

                return true;
            }
        } else {
            if (2 !== count($function)) {
                throw new \LengthException('Exactly two parameters required!');
            }

            /*
             * bug: use addHook before setColumns
            if (false === in_array($column, $this->columns)) {
                throw new \InvalidArgumentException(sprintf(
                    "Parameter column must be defined in setColumns function!\nRecived: %s\n Expected one of: %s",
                    $function[1],
                    implode(', ', $this->columns)
                ));
            }
            */
            if (false === is_callable($function)) {
                throw new \BadFunctionCallException(sprintf(
                    'Function %s in class %s is non callable!',
                    $function[1],
                    $function[0]
                ));
            }

            $this->hooks[$column] = array($function[0], $function[1]);
        }

        return $this;
    }

    /**
     * @param string $row
     *
     * @return bool
     */
    private function addRow($row)
    {
        $separator = $this->getSeparator();
        $escape = $this->getEscape();
        $hooks = $this->hooks;
        $format = $this->getFormat();
        $allowNull = $this->getAllowNull();
        $nullReplace = $this->getNullReplace();

        $tempRow = array_map(
            function ($column) use ($row, $separator, $escape, $hooks, $format, $allowNull, $nullReplace) {
                try {
                    $value = PropertyAccess::createPropertyAccessor()->getValue($row, $column);
                } catch (UnexpectedTypeException $exception) {
                    if (true === $allowNull) {
                        $value = $nullReplace;
                    } else {
                        throw $exception;
                    }
                }

                return DataExporter::escape(
                    $value,
                    $separator,
                    $escape,
                    $column,
                    $hooks,
                    $format
                );
            },
            $this->columns
        );

        switch ($this->getFormat()) {
            case 'csv':
                $this->data[] = implode($this->getSeparator(), $tempRow);
                break;
            case 'json':
                $this->data[] = array_combine($this->data[0], $tempRow);
                break;
            case 'pdf':
            case 'listData':
            case 'render':
                $this->data[] = $tempRow;
                break;
            case 'xls':
            case 'html':
                $this->data .= '<tr>';
                foreach ($tempRow as $val) {
                    $this->data .= '<td>' . $val . '</td>';
                }
                $this->data .= '</tr>';
                break;
            case 'xml':
                $this->data .= '<row>';
                $index = 0;
                foreach ($tempRow as $val) {
                    $this->data .= '<column name="' . $this->columns[$index] . '">' . $val . '</column>';
                    $index++;
                }
                $this->data .= '</row>';
                break;
        }

        return true;
    }

    /**
     * @param array $rows
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function setData($rows)
    {
        if (empty($this->columns)) {
            throw new \RuntimeException('First use setColumns to set columns to export!');
        }

        foreach ($rows as $row) {
            $this->addRow($row);
        }

        //close tags
        $this->closeData();

        return $this;
    }

    /**
     * @return $this
     */
    private function closeData()
    {
        switch ($this->getFormat()) {
            case 'json':
                //remove first row from data
                unset($this->data[0]);
                break;
            case 'xls':
                $this->closeXLS();
                break;
            case 'html':
                $this->closeHTML();
                break;
            case 'xml':
                $this->closeXML();
                break;
        }

        return $this;
    }

    /**
     * @param array $haystack
     *
     * @return mixed
     */
    private function getLastKeyFromArray(Array $haystack)
    {
        end($haystack);

        return key($haystack);
    }

    /**
     * @param array $haystack
     *
     * @return mixed
     */
    private function getFirstKeyFromArray(Array $haystack)
    {
        reset($haystack);

        return key($haystack);
    }

    /**
     * @param string  $column
     * @param integer $key
     * @param array   $columns
     *
     * @return $this
     */
    private function setColumn($column, $key, $columns)
    {
        if (true === is_integer($key)) {
            $this->columns[] = $column;
        } else {
            $this->columns[] = $key;
        }

        if (in_array($this->getFormat(), array('csv', 'json', 'xls'))) {
            $column = strip_tags($column);
        }

        if ('csv' === $this->getFormat() && false === $this->getSkipHeader()) {
            //last item
            if (isset($this->data[0])) {
                //last item
                if ($key != $this->getLastKeyFromArray($columns)) {
                    $this->data[0] = $this->data[0] . $column . $this->getSeparator();
                } else {
                    $this->data[0] = $this->data[0] . $column;
                }
            } else {
                $this->data[] = $column . $this->getSeparator();
            }
        } elseif (true === in_array($this->getFormat(), array('xls', 'html'))) {
            //first item
            if ($key === $this->getFirstKeyFromArray($columns)) {
                $this->data .= '<tr>';
            }

            $this->data .= sprintf('<td>%s</td>', $column);
            //last item
            if ($key === $this->getLastKeyFromArray($columns)) {
                $this->data .= '</tr>';
            }
        } elseif ('json' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        } elseif ('pdf' === $this->getFormat() || 'render' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        } elseif ('listData' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        }

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function setColumns(array $columns)
    {
        $format = $this->getFormat();
        if (empty($format)) {
            throw new \RuntimeException(sprintf('First use setOptions!'));
        }

        foreach ($columns as $key => $column) {
            $this->setColumn($column, $key, $columns);
        }

        return $this;
    }


    /**
     * @return string
     */
    private function prepareCSV()
    {
        return implode("\n", $this->data);
    }

    /**
     * @return string|Response
     */
    public function render()
    {
        $response = new Response;

        switch ($this->getFormat()) {
            case 'csv':
                $response->headers->set('Content-Type', 'text/csv');
                $response->setContent($this->prepareCSV());
                break;
            case 'json':
                $response->headers->set('Content-Type', 'application/json');
                //remove first row from data
                unset($this->data[0]);
                $response->setContent(json_encode($this->data));
                break;
            case 'xls':
                $response->headers->set('Content-Type', 'application/vnd.ms-excel');
                $response->setContent($this->data);
                break;
            case 'html':
                $response->headers->set('Content-Type', 'text/html');
                $response->setContent($this->data);
                break;
            case 'xml':
                $response->headers->set('Content-Type', 'application/xml');
                $response->setContent($this->data);
                break;
            case 'pdf':
                $columns = $this->data[0];
                unset($this->data[0]);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->setContent(
                    $this->knpSnappyPdf->getOutputFromHtml(
                        $this->templating->render($this->getTemplate(), array(
                                'columns'  => $columns,
                                'data' => $this->data,
                                'template_vars' => $this->getTemplateVars()
                            )),
                        $this->getPdfOptions()
                    )
                );
                break;
            case 'render':
                $columns = $this->data[0];
                unset($this->data[0]);
                $response->headers->set('Content-Type', 'text/plain');
                $response->setContent(
                    $this->templating->render($this->getTemplate(), array(
                            'columns'  => $columns,
                            'data' => $this->data,
                            'template_vars' => $this->getTemplateVars()
                        ))
                );
                break;
            case 'listData':
                $columns = $this->data[0];
                unset($this->data[0]);

                return array('columns' => $columns, 'rows' => $this->data);
        }

        if ($this->getInMemory()) {
            return $response->getContent();
        }

        $response->headers->set('Cache-Control', 'public');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->getFileName() . '"');

        return $response;
    }
}
