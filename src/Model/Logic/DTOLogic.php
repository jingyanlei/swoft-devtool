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
class DTOLogic
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
            output()->colored('Generate entity match table is empty!', 'error');
            return;
        }

        $dtoPath = "@app/Logic/Dto/";
        foreach ($tableSchemas as $tableSchema) {
            $this->readyGenerateId = false;
            $this->generateEntity($tableSchema, $pool, $dtoPath.'Response/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "Info", $modules);
            $this->generateResponseEntity($tableSchema, $pool, $dtoPath.'Response/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "Info", $modules);
            $this->generateEntity($tableSchema, $pool, $dtoPath.'Response/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "List", $modules);
            $this->generateResponseEntity($tableSchema, $pool, $dtoPath.'Response/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "List", $modules);
            $this->generateRequest($tableSchema, $pool, $dtoPath.'Request/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "Create", $modules);
            $this->generateRequest($tableSchema, $pool, $dtoPath.'Request/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "Update", $modules);
            $this->generateRequest($tableSchema, $pool, $dtoPath.'Request/'.$modules.'/'.$path, $isConfirm, $fieldPrefix, $tplDir, "PaginateQuery", $modules);
        }
    }

    /**
     * @param array  $tableSchema
     * @param string $pool
     * @param string $path
     * @param bool   $isConfirm
     * @param string $fieldPrefix
     * @param string $tplDir
     *
     * @throws DbException
     * @throws TemplateParsingException
     */
    private function generateEntity(
        array $tableSchema,
        string $pool,
        string $path,
        bool $isConfirm,
        string $fieldPrefix,
        string $tplDir,
        string $extName,
        string $modules
    ): void {
        $file   = alias($path);
        $tplDir = alias($tplDir.'/dto');

        $mappingClass = $tableSchema['mapping'];
        $config       = [
            'tplFilename' => 'dto',
            'tplDir'      => $tplDir,
            'className'   => $mappingClass,
        ];

        var_dump($tplDir.'/'.$config['tplFilename']);

        if (!is_dir($file)) {
            if (!$isConfirm && !ConsoleHelper::confirm("mkdir path $file, Ensure continue?", true)) {
                output()->writeln(' Quit, Bye!');
                return;
            }
            if (!mkdir($file, 0755, true) && !is_dir($file)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $file));
            }
        }
        $file .= sprintf('/%s%s%sEntity.php', $modules, $mappingClass, $extName);

        $columnSchemas = $this->schemaData->getSchemaColumnsData($pool, $tableSchema['name'], $fieldPrefix);

        $genSetters    = [];
        $genGetters    = [];
        $genProperties = [];
        foreach ($columnSchemas as $columnSchema) {
            $genProperties[] = $this->generateProperties($columnSchema, $tplDir);

            $genSetters[] = $this->generateSetters($columnSchema, $tplDir);
            $genGetters[] = $this->generateGetters($columnSchema, $tplDir);
        }

        $setterStr   = rtrim(implode("\n", $genSetters), "\n");
        $getterStr   = rtrim(implode("\n", $genGetters), "\n");
        $propertyStr = implode("\n", $genProperties);
        $methodStr   = sprintf("%s\n\n%s", $setterStr, $getterStr);

        $data = [
            'properties'   => $propertyStr,
            'methods'      => $methodStr,
            'tableName'    => $tableSchema['name'],
            'entityName'   => $modules.$mappingClass.$extName.'Entity',
            'namespace'    => $this->getNameSpace($path),
            'tableComment' => $tableSchema['comment'],
            'dbPool'       => $pool === Pool::DEFAULT_POOL ? '' : ', pool="' . $pool . '"',
        ];
        $gen  = new FileGenerator($config);

        $fileExists = file_exists($file);

        if (!$fileExists && !$isConfirm && !ConsoleHelper::confirm("generate entity $file, Ensure continue?", true)) {
            output()->writeln(' Quit, Bye!');
            return;
        }
        if ($fileExists && !$isConfirm
            && !ConsoleHelper::confirm(
                " entity $file already exists, Ensure continue?",
                false
            )
        ) {
            output()->writeln(' Quit, Bye!');
            return;
        }

        var_dump($file);
        if ($gen->renderas($file, $data)) {
            output()->colored(" Generate entity $file OK!", 'success');
            return;
        }

        output()->colored(" Generate entity $file Fail!", 'error');
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

    /**
     * @param array  $colSchema
     * @param string $tplDir
     *
     * @return string
     * @throws TemplateParsingException
     */
    private function generateGetters(array $colSchema, string $tplDir): string
    {
        $getterName = sprintf('get%s', ucfirst($colSchema['mappingName']));
        $config     = [
            'tplFilename' => 'getter',
            'tplDir'      => $tplDir,
        ];
        $data       = [
            'type'       => '?' . $colSchema['originPHPType'],
            'returnType' => $colSchema['phpType'],
            'methodName' => $getterName,
            'property'   => $colSchema['mappingName'],
        ];
        $gen        = new FileGenerator($config);

        return (string)$gen->render($data);
    }

    /**
     * @param array  $colSchema
     * @param string $tplDir
     *
     * @return string
     * @throws TemplateParsingException
     */
    private function generateSetters(array $colSchema, string $tplDir): string
    {
        $setterName = sprintf('set%s', ucfirst($colSchema['mappingName']));

        $config = [
            'tplFilename' => 'setter',
            'tplDir'      => $tplDir,
        ];
        // nullable
        $type = $colSchema['is_nullable'] ? '?' . $colSchema['originPHPType'] : $colSchema['originPHPType'];
        $data = [
            'type'       => $type,
            'paramType'  => $colSchema['phpType'],
            'methodName' => $setterName,
            'paramName'  => sprintf('$%s', $colSchema['mappingName']),
            'property'   => $colSchema['mappingName'],
        ];
        $gen  = new FileGenerator($config);

        return (string)$gen->render($data);
    }

    private function generateResponseEntity(
        array $tableSchema,
        string $pool,
        string $path,
        bool $isConfirm,
        string $fieldPrefix,
        string $tplDir,
        string $extName,
        string $modules
    ): void {
        $file   = alias($path);
        $tplDir = alias($tplDir.'/dto');

        $mappingClass = $tableSchema['mapping'];
        $config       = [
            'tplFilename' => 'dto-response',
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
        $file .= sprintf('/%s%s%sResponse.php', $modules, $mappingClass, $extName);

        $columnSchemas = $this->schemaData->getSchemaColumnsData($pool, $tableSchema['name'], $fieldPrefix);

        $genSetters    = [];
        $genGetters    = [];
        $genProperties = [];
        foreach ($columnSchemas as $columnSchema) {
            $genProperties[] = $this->generateProperties($columnSchema, $tplDir);

            $genSetters[] = $this->generateSetters($columnSchema, $tplDir);
            $genGetters[] = $this->generateGetters($columnSchema, $tplDir);
        }

        $setterStr   = rtrim(implode("\n", $genSetters), "\n");
        $getterStr   = rtrim(implode("\n", $genGetters), "\n");
        $propertyStr = implode("\n", $genProperties);
        $methodStr   = sprintf("%s\n\n%s", $setterStr, $getterStr);

        $data = [
//            'properties'   => $propertyStr,
//            'methods'      => $methodStr,
            'tableName'    => $tableSchema['name'],
            'entityName'   => $modules.$mappingClass.$extName.'Response',
            'extendsName'   => $modules.$mappingClass.$extName.'Entity',
            'namespace'    => $this->getNameSpace($path),
            'tableComment' => $tableSchema['comment'],
            'dbPool'       => $pool === Pool::DEFAULT_POOL ? '' : ', pool="' . $pool . '"',
        ];
        $gen  = new FileGenerator($config);

        $fileExists = file_exists($file);

        if (!$fileExists && !$isConfirm && !ConsoleHelper::confirm("generate entity $file, Ensure continue?", true)) {
            output()->writeln(' Quit, Bye!');
            return;
        }
        if ($fileExists && !$isConfirm
            && !ConsoleHelper::confirm(
                " entity $file already exists, Ensure continue?",
                false
            )
        ) {
            output()->writeln(' Quit, Bye!');
            return;
        }

        var_dump($file);
        if ($gen->renderas($file, $data)) {
            output()->colored(" Generate entity $file OK!", 'success');
            return;
        }

        output()->colored(" Generate entity $file Fail!", 'error');
    }

    private function generateRequest(
        array $tableSchema,
        string $pool,
        string $path,
        bool $isConfirm,
        string $fieldPrefix,
        string $tplDir,
        string $extName,
        string $modules
    ): void {
        $file   = alias($path);
        $tplDir = alias($tplDir.'/dto');

        $mappingClass = $tableSchema['mapping'];
        $config       = [
            'tplFilename' => 'dto-request-'.$extName,
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
        $file .= sprintf('/%s%s%sRequest.php', $modules, $mappingClass, $extName);

        $columnSchemas = $this->schemaData->getSchemaColumnsData($pool, $tableSchema['name'], $fieldPrefix);

        $genSetters    = [];
        $genGetters    = [];
        $genProperties = [];
        $exclusion = [
            'id',
            'created_at',
            'updated_at',
            'is_delete'
        ];
        foreach ($columnSchemas as $columnSchema) {
            if ($extName == 'Create') {
                if (in_array($columnSchema['name'], $exclusion)) {
                    continue;
                }
            }
            $genProperties[] = $this->generateProperties($columnSchema, $tplDir);
            $genSetters[] = $this->generateSetters($columnSchema, $tplDir);
            $genGetters[] = $this->generateGetters($columnSchema, $tplDir);
        }

        $setterStr   = rtrim(implode("\n", $genSetters), "\n");
        $getterStr   = rtrim(implode("\n", $genGetters), "\n");
        $propertyStr = implode("\n", $genProperties);
        $methodStr   = sprintf("%s\n\n%s", $setterStr, $getterStr);

        $data = [
            'properties'   => $propertyStr,
            'methods'      => $methodStr,
            'tableName'    => $tableSchema['name'],
            'entityName'   => $modules.$mappingClass.$extName.'Request',
            'namespace'    => $this->getNameSpace($path),
            'tableComment' => $tableSchema['comment'],
            'dbPool'       => $pool === Pool::DEFAULT_POOL ? '' : ', pool="' . $pool . '"',
        ];
        $gen  = new FileGenerator($config);

        $fileExists = file_exists($file);

        if (!$fileExists && !$isConfirm && !ConsoleHelper::confirm("generate entity $file, Ensure continue?", true)) {
            output()->writeln(' Quit, Bye!');
            return;
        }
        if ($fileExists && !$isConfirm
            && !ConsoleHelper::confirm(
                " entity $file already exists, Ensure continue?",
                false
            )
        ) {
            output()->writeln(' Quit, Bye!');
            return;
        }

        var_dump($file);
        if ($gen->renderas($file, $data)) {
            output()->colored(" Generate entity $file OK!", 'success');
            return;
        }

        output()->colored(" Generate entity $file Fail!", 'error');
    }
}
