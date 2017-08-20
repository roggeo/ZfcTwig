<?php
namespace ZfcTwigTest\View;

use PHPUnit\Framework\TestCase;
use Twig_Environment;
use Twig_Loader_Array;
use Twig_Loader_Chain;
use Zend\View\Model\ModelInterface;
use Zend\View\View;
use Zend\View\ViewEvent;
use ZfcTwig\View\TwigRenderer;
use ZfcTwig\View\TwigStrategy;
use ZfcTwig\View\TwigResolver;

class TwigStrategyTest extends TestCase
{
    /** @var  TwigRenderer */
    protected $renderer;

    /** @var  TwigStrategy */
    protected $strategy;

    public function setUp()
    {
        parent::setUp();

        $chain = new Twig_Loader_Chain();
        $chain->addLoader(new Twig_Loader_Array(['key1' => 'var1']));
        $environment = new Twig_Environment($chain);
        $this->renderer = new TwigRenderer(new View, $chain, $environment, new TwigResolver($environment));
        $this->strategy = new TwigStrategy($this->renderer);
    }

    public function testSelectRendererWhenTemplateFound()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|ModelInterface $model */
        $model = $this->getMockBuilder(ModelInterface::class)->getMock();
        $model->expects($this->at(0))
              ->method('getTemplate')
              ->willReturn('key1');

        $event = new ViewEvent;
        $event->setModel($model);

        $result = $this->strategy->selectRenderer($event);
        $this->assertSame($this->renderer, $result);
    }
}
