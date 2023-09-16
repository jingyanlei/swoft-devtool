<?php declare(strict_types=1);

namespace SwoftTest\Devtool\Unit;

use PHPUnit\Framework\TestCase;
use Swoft\Devtool\Command\EntityCommand;
use Swoft\Devtool\FileGenerator;

/**
 * Class FileGeneratorTest
 */
class EntityTest extends TestCase
{
    /**
     * @throws \Leuffen\TextTemplate\TemplateParsingException
     */
    public function testGen()
    {
        $data = [
            'prefix'    => './path',
            'className' => 'DemoController',
            'namespace' => 'App\Controller',
        ];

        $entity = new EntityCommand([
            'tplDir' => __DIR__ . '/res',
        ]);
        $entity->create();
    }
}
