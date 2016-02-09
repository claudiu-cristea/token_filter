<?php

/**
 * @file
 * Contains \Drupal\token_filter\Plugin\Filter\TokenFilter.
 */

namespace Drupal\token_filter\Plugin\Filter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter that replaces global and entity tokens with their values.
 *
 * @Filter(
 *   id = "token_filter",
 *   title = @Translation("Replaces global and entity tokens with their
 *   values"), type =
 *   Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   settings = { }
 * )
 */
class TokenFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a token filter plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
   *   The token entity mapper service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Token $token, TokenEntityMapperInterface $token_entity_mapper, RendererInterface $renderer, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
    $this->tokenEntityMapper = $token_entity_mapper;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('token.entity_mapper'),
      $container->get('renderer'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $data = [];

    // Attempt to figure out the current context based on the current backtrace.
    $backtrace = debug_backtrace();
    // Pop off this current function in the stack.
    array_shift($backtrace);
    foreach ($backtrace as $caller) {
      if ($caller['function'] == 'render' && isset($caller['object']) && $caller['object'] instanceof ThemeManagerInterface && $caller['args'][0] == 'field' && isset($caller['args'][1]['#object']) && $caller['args'][1]['#object'] instanceof ContentEntityInterface) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $caller['args'][1]['#object'];
        $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity->getEntityTypeId());
        $data[$token_type] = $entity;
        break;
      }
    }

    return new FilterProcessResult($this->token->replace($text, $data), [], ['langcode' => $langcode]);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $build = [];
    $build[] = ['#markup' => $this->t('Global and entity tokens are replaced with their values.')];

    $token_types = [];
    /** @var \Drupal\Core\Routing\RouteMatchInterface $match */
    $route_match = \Drupal::routeMatch();
    $parameters = $route_match->getParameters();
    foreach ($parameters as $parameter) {
      if ($parameter instanceof ContentEntityInterface) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $parameter */
        $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($parameter->getEntityTypeId());
        $token_types[] = $token_type;
      }
    }

    $build[] = [
      '#prefix' => ' ',
      '#theme'  => 'token_tree_link',
      '#token_types' => $token_types,
    ];

    return $this->renderer->render($build);
  }

}
