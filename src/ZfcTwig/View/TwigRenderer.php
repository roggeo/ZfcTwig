<?php

namespace ZfcTwig\View;

use Twig\Environment;
use Twig\Loader;
use Zend\View\Exception;
use Zend\View\HelperPluginManager;
use Zend\View\Model\ModelInterface;
use Zend\View\Renderer\RendererInterface;
use Zend\View\Renderer\TreeRendererInterface;
use Zend\View\Resolver\ResolverInterface;
use Zend\View\View;

class TwigRenderer implements RendererInterface, TreeRendererInterface
{
    /**
     * @var bool
     */
    protected $canRenderTrees = true;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var HelperPluginManager
     */
    protected $helperPluginManager;

    /**
     * @var HelperPluginManager
     */
    protected $zendHelperPluginManager;

    /**
     * @var Loader\ChainLoader
     */
    protected $loader;

    /**
     * @var TwigResolver
     */
    protected $resolver;

    /**
     * @var \Zend\View\View
     */
    protected $view;

    /**
     * @var array Cache for the plugin call
     */
    private $__pluginCache = [];

    /**
     * @param View $view
     * @param Loader\ChainLoader $loader
     * @param Environment $environment
     * @param TwigResolver $resolver
     */
    public function __construct(
        View $view,
        Loader\ChainLoader $loader,
        Environment $environment,
        TwigResolver $resolver
    ) {
        $this->environment = $environment;
        $this->loader      = $loader;
        $this->resolver    = $resolver;
        $this->view        = $view;
    }

    /**
     * Overloading: proxy to helpers
     *
     * Proxies to the attached plugin manager to retrieve, return, and potentially
     * execute helpers.
     *
     * * If the helper does not define __invoke, it will be returned
     * * If the helper does define __invoke, it will be called as a functor
     *
     * @param  string $method
     * @param  array $argv
     * @return mixed
     */
    public function __call($method, $argv)
    {
        if (!isset($this->__pluginCache[$method])) {
            $this->__pluginCache[$method] = $this->plugin($method);
        }
        if (is_callable($this->__pluginCache[$method])) {
            return call_user_func_array($this->__pluginCache[$method], $argv);
        }
        return $this->__pluginCache[$method];
    }

    /**
     * @param boolean $canRenderTrees
     * @return TwigRenderer
     */
    public function setCanRenderTrees($canRenderTrees)
    {
        $this->canRenderTrees = $canRenderTrees;
        return $this;
    }

    /**
     * @return boolean
     */
    public function canRenderTrees()
    {
        return $this->canRenderTrees;
    }

    /**
     * Get plugin instance, proxy to HelperPluginManager::get
     *
     * @param  string     $name Name of plugin to return
     * @param  null|array $options Options to pass to plugin constructor (if not already instantiated)
     * @return \Zend\View\Helper\AbstractHelper
     */
    public function plugin($name, array $options = null)
    {
        $helper = $this->getHelperPluginManager()
                    ->setRenderer($this);

        if ($helper->has($name)) {
            return $helper->get($name, $options);
        }

        return $this->getZendHelperPluginManager()->get($name, $options);
    }

    /**
     * Can the template be rendered?
     *
     * @param string $name
     * @return bool
     * @see \ZfcTwig\Twig\Environment::canLoadTemplate()
     */
    public function canRender($name)
    {
        return $this->loader->exists($name);
    }

    /**
     * Return the template engine object, if any
     *
     * If using a third-party template engine, such as Smarty, patTemplate,
     * phplib, etc, return the template engine object. Useful for calling
     * methods on these objects, such as for setting filters, modifiers, etc.
     *
     * @return Environment
     */
    public function getEngine()
    {
        return $this->environment;
    }

    /**
     * Set the resolver used to map a template name to a resource the renderer may consume.
     *
     * @param  ResolverInterface $resolver
     * @return TwigRenderer
     */
    public function setResolver(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * @param HelperPluginManager $helperPluginManager
     * @return TwigRenderer
     */
    public function setHelperPluginManager(HelperPluginManager $helperPluginManager)
    {
        $helperPluginManager->setRenderer($this);
        $this->helperPluginManager = $helperPluginManager;
        return $this;
    }

    /**
     * @return \Zend\View\HelperPluginManager
     */
    public function getHelperPluginManager()
    {
        return $this->helperPluginManager;
    }

    /**
     * @return HelperPluginManager
     */
    public function getZendHelperPluginManager()
    {
        return $this->zendHelperPluginManager;
    }

    /**
     * @param HelperPluginManager $zendHelperPluginManager
     * @return self
     */
    public function setZendHelperPluginManager($zendHelperPluginManager)
    {
        $this->zendHelperPluginManager = $zendHelperPluginManager;
        return $this;
    }

    /**
     * Processes a view script and returns the output.
     *
     * @param  string|ModelInterface   $nameOrModel The script/resource process, or a view model
     * @param  null|array|\ArrayAccess $values      Values to use during rendering
     * @return string|null The script output.
     * @throws \Zend\View\Exception\DomainException
     */
    public function render($nameOrModel, $values = null)
    {
        $model = null;

        if ($nameOrModel instanceof ModelInterface) {
            $model       = $nameOrModel;
            $nameOrModel = $model->getTemplate();

            if (empty($nameOrModel)) {
                throw new Exception\DomainException(sprintf(
                    '%s: received View Model argument, but template is empty', __METHOD__
                ));
            }

            $values = (array) $model->getVariables();
        }

        if (!$this->canRender($nameOrModel)) {
            return null;
        }

        if (null === $values) {
            $values = [];
        }

        if ($model && $this->canRenderTrees() && $model->hasChildren()) {
            if (!isset($values['content'])) {
                $values['content'] = '';
            }
            foreach ($model as $child) {
                /** @var ModelInterface $child */
                if ($this->canRender($child->getTemplate())) {
                    $template = $this->resolver->resolve($child->getTemplate(), $this);

                    $childValues = (array) $child->getVariables();
                    if ($child->hasChildren()) {
                        foreach ($child->getChildren() as $grandChild) {
                            /** @var ModelInterface $grandChild */
                            $childValues[$grandChild->captureTo()] = $this->render($grandChild);
                        }
                    }

                    return $template->render($childValues);
                }
                $child->setOption('has_parent', true);
                $values['content'] .= $this->view->render($child);
            }
        }

        /** @var $template \Twig\Template */
        $template = $this->resolver->resolve($nameOrModel, $this);
        return $template->render((array) $values);
    }
}
