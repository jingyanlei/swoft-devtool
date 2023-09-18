<?php declare(strict_types=1);
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace Swoft\Devtool\Command;

use Swoft\Bean\Annotation\Mapping\Inject;
use Swoft\Console\Annotation\Mapping\Command;
use Swoft\Console\Annotation\Mapping\CommandArgument;
use Swoft\Console\Annotation\Mapping\CommandMapping;
use Swoft\Console\Annotation\Mapping\CommandOption;
use Swoft\Db\Pool;
use Swoft\Devtool\Model\Logic\ControllerLogic;
use Swoft\Devtool\Model\Logic\DaoLogic;
use Swoft\Devtool\Model\Logic\DTOLogic;
use Swoft\Devtool\Model\Logic\ServiceLogic;
use Throwable;
use function input;

/**
 * Generate entity class by database table names[by <cyan>devtool</cyan>]
 *
 * @Command()
 * @since 2.0
 */
class GenCommand
{
    /**
     * @Inject()
     *
     * @var DTOLogic
     */
    private $logic;

    /**
     * @Inject()
     * @var DaoLogic
     */
    private $daoLogic;

    /**
     * @Inject()
     * @var ServiceLogic
     */
    private $serviceLogic;

    /**
     * @Inject()
     * @var ControllerLogic
     */
    private $controllerLogic;

    /**
     * Generate database dto
     *
     * @CommandMapping(alias="dto")
     * @CommandArgument(name="table", desc="database table names", type="string")
     * @CommandOption(name="table", desc="database table names", type="string")
     * @CommandOption(name="pool", desc="choose default database pool", type="string", default="db.pool")
     * @CommandOption(name="path", desc="generate entity file path", type="string", default="@app/Logic/Dto")
     * @CommandOption(name="y", desc="auto generate", type="string")
     * @CommandOption(name="field_prefix", desc="database field prefix ,alias is 'fp'", type="string")
     * @CommandOption(name="table_prefix", desc="like match database table prefix, alias is 'tp'", type="string")
     * @CommandOption(name="exclude", desc="expect generate database table entity, alias is 'exc'", type="string")
     * @CommandOption(name="td", desc="generate entity template path",type="string", default="@devtool/devtool/resource/template")
     * @CommandOption(name="remove_prefix", desc="remove table prefix ,alias is 'rp'",type="string")
     * @CommandOption(name="modules", desc="modules, alias is 'm'",type="string")
     * @return void
     */
    public function create(): void
    {
        $table        = input()->get('table', input()->getOpt('table'));
        $pool         = input()->getOpt('pool', Pool::DEFAULT_POOL);
        $path         = input()->getOpt('path', '@app/Model/Entity');
        $isConfirm    = input()->getOpt('y', false);
        $fieldPrefix  = input()->getOpt('field_prefix', input()->getOpt('fp'));
        $tablePrefix  = input()->getOpt('table_prefix', input()->getOpt('tp'));
        $exclude      = input()->getOpt('exc', input()->getOpt('exclude'));
        $tplDir       = input()->getOpt('td', '@devtool/devtool/resource/template');
        $removePrefix = input()->getOpt('remove_prefix', input()->getOpt('rp'));
        $modules      = input()->getOpt('modules', input()->getOpt('m'), 'Seller');

        try {
            $this->logic->create([
                (string)$table,
                (string)$tablePrefix,
                (string)$fieldPrefix,
                (string)$exclude,
                (string)$pool,
                (string)$path,
                (bool)$isConfirm,
                (string)$tplDir,
                (string)$removePrefix,
                (string)$modules
            ]);
        } catch (Throwable $exception) {
            output()->colored($exception->getMessage(), 'error');
        }
    }

    /**
     * Generate database dao
     *
     * @CommandMapping(alias="dao")
     * @CommandArgument(name="table", desc="database table names", type="string")
     * @CommandOption(name="table", desc="database table names", type="string")
     * @CommandOption(name="pool", desc="choose default database pool", type="string", default="db.pool")
     * @CommandOption(name="path", desc="generate entity file path", type="string", default="@app/Logic/Dto")
     * @CommandOption(name="y", desc="auto generate", type="string")
     * @CommandOption(name="field_prefix", desc="database field prefix ,alias is 'fp'", type="string")
     * @CommandOption(name="table_prefix", desc="like match database table prefix, alias is 'tp'", type="string")
     * @CommandOption(name="exclude", desc="expect generate database table entity, alias is 'exc'", type="string")
     * @CommandOption(name="td", desc="generate entity template path",type="string", default="@devtool/devtool/resource/template")
     * @CommandOption(name="remove_prefix", desc="remove table prefix ,alias is 'rp'",type="string")
     * @CommandOption(name="modules", desc="modules, alias is 'm'",type="string")
     * @return void
     */
    public function dao(): void
    {
        $table        = input()->get('table', input()->getOpt('table'));
        $pool         = input()->getOpt('pool', Pool::DEFAULT_POOL);
        $path         = input()->getOpt('path', '@app/Model/Entity');
        $isConfirm    = input()->getOpt('y', false);
        $fieldPrefix  = input()->getOpt('field_prefix', input()->getOpt('fp'));
        $tablePrefix  = input()->getOpt('table_prefix', input()->getOpt('tp'));
        $exclude      = input()->getOpt('exc', input()->getOpt('exclude'));
        $tplDir       = input()->getOpt('td', '@devtool/devtool/resource/template');
        $removePrefix = input()->getOpt('remove_prefix', input()->getOpt('rp'));
        $modules      = input()->getOpt('modules', input()->getOpt('m'));

        try {
            $this->daoLogic->create([
                (string)$table,
                (string)$tablePrefix,
                (string)$fieldPrefix,
                (string)$exclude,
                (string)$pool,
                (string)$path,
                (bool)$isConfirm,
                (string)$tplDir,
                (string)$removePrefix,
                (string)$modules
            ]);
        } catch (Throwable $exception) {
            output()->colored($exception->getMessage(), 'error');
        }
    }

    /**
     * Generate database service
     *
     * @CommandMapping(alias="service")
     * @CommandArgument(name="table", desc="database table names", type="string")
     * @CommandOption(name="table", desc="database table names", type="string")
     * @CommandOption(name="pool", desc="choose default database pool", type="string", default="db.pool")
     * @CommandOption(name="path", desc="generate entity file path", type="string", default="@app/Logic/Dto")
     * @CommandOption(name="y", desc="auto generate", type="string")
     * @CommandOption(name="field_prefix", desc="database field prefix ,alias is 'fp'", type="string")
     * @CommandOption(name="table_prefix", desc="like match database table prefix, alias is 'tp'", type="string")
     * @CommandOption(name="exclude", desc="expect generate database table entity, alias is 'exc'", type="string")
     * @CommandOption(name="td", desc="generate entity template path",type="string", default="@devtool/devtool/resource/template")
     * @CommandOption(name="remove_prefix", desc="remove table prefix ,alias is 'rp'",type="string")
     * @CommandOption(name="modules", desc="modules, alias is 'm'",type="string")
     * @return void
     */
    public function service(): void
    {
        $table        = input()->get('table', input()->getOpt('table'));
        $pool         = input()->getOpt('pool', Pool::DEFAULT_POOL);
        $path         = input()->getOpt('path', '@app/Model/Entity');
        $isConfirm    = input()->getOpt('y', false);
        $fieldPrefix  = input()->getOpt('field_prefix', input()->getOpt('fp'));
        $tablePrefix  = input()->getOpt('table_prefix', input()->getOpt('tp'));
        $exclude      = input()->getOpt('exc', input()->getOpt('exclude'));
        $tplDir       = input()->getOpt('td', '@devtool/devtool/resource/template');
        $removePrefix = input()->getOpt('remove_prefix', input()->getOpt('rp'));
        $modules      = input()->getOpt('modules', input()->getOpt('m'));

        try {
            $this->serviceLogic->create([
                (string)$table,
                (string)$tablePrefix,
                (string)$fieldPrefix,
                (string)$exclude,
                (string)$pool,
                (string)$path,
                (bool)$isConfirm,
                (string)$tplDir,
                (string)$removePrefix,
                (string)$modules
            ]);
        } catch (Throwable $exception) {
            output()->colored($exception->getMessage(), 'error');
        }
    }

    /**
     * Generate database controller
     *
     * @CommandMapping(alias="controller")
     * @CommandArgument(name="table", desc="database table names", type="string")
     * @CommandOption(name="table", desc="database table names", type="string")
     * @CommandOption(name="pool", desc="choose default database pool", type="string", default="db.pool")
     * @CommandOption(name="path", desc="generate entity file path", type="string", default="@app/Logic/Dto")
     * @CommandOption(name="y", desc="auto generate", type="string")
     * @CommandOption(name="field_prefix", desc="database field prefix ,alias is 'fp'", type="string")
     * @CommandOption(name="table_prefix", desc="like match database table prefix, alias is 'tp'", type="string")
     * @CommandOption(name="exclude", desc="expect generate database table entity, alias is 'exc'", type="string")
     * @CommandOption(name="td", desc="generate entity template path",type="string", default="@devtool/devtool/resource/template")
     * @CommandOption(name="remove_prefix", desc="remove table prefix ,alias is 'rp'",type="string")
     * @CommandOption(name="modules", desc="modules, alias is 'm'",type="string")
     * @return void
     */
    public function controller(): void
    {
        $table        = input()->get('table', input()->getOpt('table'));
        $pool         = input()->getOpt('pool', Pool::DEFAULT_POOL);
        $path         = input()->getOpt('path', '@app/Model/Entity');
        $isConfirm    = input()->getOpt('y', false);
        $fieldPrefix  = input()->getOpt('field_prefix', input()->getOpt('fp'));
        $tablePrefix  = input()->getOpt('table_prefix', input()->getOpt('tp'));
        $exclude      = input()->getOpt('exc', input()->getOpt('exclude'));
        $tplDir       = input()->getOpt('td', '@devtool/devtool/resource/template');
        $removePrefix = input()->getOpt('remove_prefix', input()->getOpt('rp'));
        $modules      = input()->getOpt('modules', input()->getOpt('m'));

        try {
            $this->controllerLogic->create([
                (string)$table,
                (string)$tablePrefix,
                (string)$fieldPrefix,
                (string)$exclude,
                (string)$pool,
                (string)$path,
                (bool)$isConfirm,
                (string)$tplDir,
                (string)$removePrefix,
                (string)$modules
            ]);
        } catch (Throwable $exception) {
            output()->colored($exception->getMessage(), 'error');
        }
    }

}

