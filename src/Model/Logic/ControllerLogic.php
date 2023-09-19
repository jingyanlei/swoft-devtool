<?php declare(strict_types=1);
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace Swoft\Devtool\Model\Logic;

use Leuffen\TextTemplate\TemplateParsingException;
use RuntimeException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Annotation\Mapping\Inject;
use Swoft\Db\Exception\DbException;
use Swoft\Db\Pool;
use Swoft\Devtool\FileGenerator;
use Swoft\Devtool\Helper\ConsoleHelper;
use Swoft\Devtool\Model\Dao\MigrateDao;
use Swoft\Devtool\Model\Data\SchemaData;
use function alias;
use function array_filter;
use function implode;
use function in_array;
use function is_dir;
use function output;
use function rtrim;
use function sprintf;
use function str_replace;
use function strpos;
use function trim;
use function ucfirst;

/**
 * EntityLogic
 * @Bean()
 */
class ControllerLogic
{
    /**
     * @Inject()
     *
     * @var SchemaData
     */
    private $schemaData;

    /**
     * @var bool
     */
    private $readyGenerateId = false;

    /**
     * Generate entity
     *
     * @param array $params
     *
     * @throws DbException
     * @throws TemplateParsingException
     */
    public function create(array $params): void
    {
        [$table, $tablePrefix, $fieldPrefix, $exclude, $pool, $path, $isConfirm, $tplDir, $removePrefix, $modules] = $params;

        // Filter system table
        $exclude   = explode(',', $exclude);
        $exclude[] = MigrateDao::tableName();
        $exclude   = implode(',', array_filter($exclude));

        $tableSchemas = $this->schemaData->getSchemaTableData($pool, $table, $exclude, $tablePrefix, $removePrefix);
        if (empty($tableSchemas)) {
            output()->colored('Generate controller match table is empty!', 'error');
            return;
        }

        $basePath = "@app/Http/Controller";
        foreach ($tableSchemas as $tableSchema) {
            $this->readyGenerateId = false;
            $this->generateDao($tableSchema, $pool, $basePath.'/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, '', $path, $modules);
        }
    }

    private function generateDao(
        array $tableSchema,
        string $pool,
        string $path,
        bool $isConfirm,
        string $fieldPrefix,
        string $tplDir,
        string $extName,
        string $mPath,
        string $modules
    ): void {
        $file   = alias($path);
        $tplDir = alias($tplDir.'/controller');

        $mappingClass = $tableSchema['mapping'];
        $tplName = (empty($modules)) ? '' : '-'.$modules;
        $config       = [
            'tplFilename' => 'controller'.lcfirst($tplName),
            'tplDir'      => $tplDir,
            'className'   => $mappingClass,
        ];

        if (!is_dir($file)) {
            if (!$isConfirm && !ConsoleHelper::confirm("mkdir path $file, Ensure continue?", true)) {
                output()->writeln(' Quit, Bye!');
                return;
            }
            if (!mkdir($file, 0755, true) && !is_dir($file)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $file));
            }
        }
        $file .= sprintf('/%s%sController.php', $mappingClass, $extName);

        $columnSchemas = $this->schemaData->getSchemaColumnsData($pool, $tableSchema['name'], $fieldPrefix);

        $request = 'use App\Logic\Dto\Request\%s\%s\%s%s%sRequest;';
        $useNamespace = sprintf($request, $modules, $mPath, $modules, $mappingClass, 'Create').PHP_EOL;
        $useNamespace .= sprintf($request, $modules, $mPath, $modules, $mappingClass, 'Update').PHP_EOL;
        $useNamespace .= sprintf($request, $modules, $mPath, $modules, $mappingClass, 'PaginateQuery').PHP_EOL;
        $useNamespace .= sprintf('use App\Logic\Service\%s\%sService;', $mPath, $modules.$mappingClass).PHP_EOL;

        $data = [
            'tableName'    => $tableSchema['name'],
            'entityName'   => $mappingClass.$extName.'Controller',
            'dao'          => $mappingClass.$extName.'Dao',
            'namespace'    => $this->getNameSpace($path),
            'tableComment' => $tableSchema['comment'],
            'dbPool'       => $pool === Pool::DEFAULT_POOL ? '' : ', pool="' . $pool . '"',
            'useNamespace' => $useNamespace,
            'mappingClass' => $mappingClass,
            'mappingClassLC' => lcfirst($mappingClass),
            'api'          => 'Api',
            'seller'       => 'Seller',
            'code'         => '{code}',
            'modules'      => lcfirst($modules),
            'mPath'      => lcfirst($mPath),
        ];
        $gen  = new FileGenerator($config);

        $fileExists = file_exists($file);

        if (!$fileExists && !$isConfirm && !ConsoleHelper::confirm("generate controller $file, Ensure continue?", true)) {
            output()->writeln(' Quit, Bye!');
            return;
        }
        if ($fileExists && !$isConfirm
            && !ConsoleHelper::confirm(
                " controller $file already exists, Ensure continue?",
                false
            )
        ) {
            output()->writeln(' Quit, Bye!');
            return;
        }

        if ($gen->renderas($file, $data)) {
            output()->colored(" Generate controller $file OK!", 'success');
            return;
        }

        output()->colored(" Generate controller $file Fail!", 'error');
    }

    /**
     * Get file namespace
     *
     * @param string $path
     *
     * @return string
     */
    private function getNameSpace(string $path): string
    {
        $path = str_replace(['@', '/'], ['', '\\'], $path);
        $path = ucfirst($path);

        return $path;
    }

    /**
     * @param array  $colSchema
     * @param string $tplDir
     *
     * @return string
     * @throws TemplateParsingException
     */
    private function generateProperties(array $colSchema, string $tplDir): string
    {
        $entityConfig = [
            'tplFilename' => 'property',
            'tplDir'      => $tplDir,
        ];

        // id
        $id = '*';
        if (!empty($colSchema['key']) && !$this->readyGenerateId && $colSchema['key'] === 'PRI') {
            // Is auto increment
            $auto = $colSchema['extra'] && strpos($colSchema['extra'], 'auto_increment') !== false ? '' :
                'incrementing=false';

            // builder @id
            $id                    = "* @Id($auto)";
            $this->readyGenerateId = true;
        }

        $mappingName = $colSchema['mappingName'];
        $fieldName   = $colSchema['name'];

        // is need map
        $prop = $mappingName === $fieldName ? '' : sprintf('prop="%s"', $mappingName);

        // column name
        $columnName = $mappingName === $fieldName ? '' : sprintf('name="%s"', $fieldName);

        // is need hidden
        $hidden = in_array($mappingName, ['password', 'pwd']) ? 'hidden=true' : '';

        $columnDetail = array_filter([$columnName, $prop, $hidden]);
        $data         = [
            'type'         => $colSchema['phpType'],
            'propertyName' => sprintf('$%s', $mappingName),
            'columnDetail' => $columnDetail ? implode(', ', $columnDetail) : '',
            'id'           => $id,
            'comment'      => trim($colSchema['columnComment']),
        ];

        $gen          = new FileGenerator($entityConfig);
        $propertyCode = $gen->render($data);

        return (string)$propertyCode;
    }
}
