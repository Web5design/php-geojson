<?php

namespace Affinity\GeoJSON;

/**
 *
 */
class Resource {

  /* */
  protected $endpoint;

  /* */
  protected $factory_info;

  /* */
  protected $factory;

  /* */
  protected $geometries = array();

  /* */
  protected $properties = array();

  /* */
  protected $features = array();

  /* */
  protected $processed = FALSE;

  /* */
  protected $route = "/";

  /* */
  protected $data;

  /**
   *
   */
  public function __construct($endpoint, $info, $data = NULL) {
    $this->factory_info = $info;

    $this->endpoint = $endpoint;
    $this->route = $info['route'];
    $this->data = $data;
  }

  /**
   *
   */
  protected function ensureFactory() {
    if (!$this->factory) {
      $class = new \ReflectionClass($this->factory_info['factory']);
      if (!$class->isSubclassOf('Affinity\GeoJSON\FeatureFactory'))
        throw new \RuntimeException('Invalid feature factory.');
      $this->factory = $class->newInstance($this->factory_info['factory args']);
    }
  }

  /**
   *
   */
  protected function process() {
    $this->ensureFactory();

    $this->processed = TRUE;
    $this->features = array();

    if (is_callable($this->data)) {
      $this->data = call_user_func($this->data);
    }

    foreach ($this->data as $key => $item) {
      $this->geometries[$key] = $this->factory->geometry($item);
      $this->properties[$key] = $this->factory->properties($item);
    }
  }

  /**
   *
   */
  public function uri() {
    $route = $this->route;
    $args = func_get_args();
    if (!empty($args)) {
      $pieces = explode('/', $route);
      foreach ($pieces as $index => $piece) {
        if ($piece[0] === '%') $pieces[$index] = array_shift($args);
      }
      $route = implode($pieces, '/');
    }
    return "{$this->endpoint}/{$route}";
  }

  /**
   *
   */
  protected function geometry() {
    if (!$this->processed || $reset) {
      $this->process();
    }
    geophp_load();
    return \geoPHP::geometryReduce(array_values($this->geometries));
  }

  public function getBBox() {
    return $this->geometry()->getBBox();
  }

  /**
   *
   */
  public function geojson($reset = FALSE) {
    if (!$this->processed || $reset) {
      $this->process();
    }

    if (!empty($this->data) && empty($this->features) || $reset) {
      foreach ($this->data as $key => $item) {
        $geom = $this->geometries[$key];
        $properties = $this->properties[$key];
        $this->features[] = $this->feature($geom, $properties);
      }
    }

    if (count($this->features) === 1) {
      return array_shift($this->features);
    }

    return $this->featureCollection($this->features);
  }

  /**
   *
   */
  public function featureCollection(array $features) {
    $collection = new \stdclass();
    $collection->type = 'FeatureCollection';
    $collection->features = $features;
    return $collection;
  }

  /**
   * Creates a GeoJSON Feature.
   *
   * @param Geometry $geometry
   *    A GeoPHP Geometry object representing the geo data of the object.
   *
   * @param Array $properties
   *    An array of properties (key, value) to be included in the feature.
   *
   * @return
   *    An array structured as a GeoJSON Feature.
   */
  public function feature($geometry, Array $properties = array()) {
    $feature = new \stdclass();
    $feature->type = 'Feature';
    $feature->geometry = is_null($geometry) ? NULL : $geometry->out('json', TRUE);
    $feature->properties = $properties;
    return $feature;
  }

  public function endpoint() {
    return $this->endpoint;
  }

}
