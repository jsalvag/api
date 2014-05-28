<?php namespace Dingo\Api\Http;

use RuntimeException;
use Dingo\Api\Transformer\Factory;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Contracts\ArrayableInterface;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Response extends IlluminateResponse {

	/**
	 * Array of registered formatters.
	 * 
	 * @var array
	 */
	protected static $formatters = [];

	/**
	 * Transformer factory instance.
	 * 
	 * @var \Dingo\Api\Transformer\Factory
	 */
	protected static $transformer;

	/**
	 * Make an API response from an existing Illuminate response.
	 * 
	 * @param  \Illuminate\Http\Response  $response
	 * @return \Dingo\Api\Http\Response
	 */
	public static function makeFromExisting(IlluminateResponse $response)
	{
		return new static($response->getOriginalContent(), $response->getStatusCode(), $response->headers->all());
	}

	/**
	 * Process the API response.
	 * 
	 * @return \Dingo\Api\Http\Response
	 */
	public function morph($format = 'json')
	{
		$content = $this->getOriginalContent();

		if (static::$transformer->transformableResponse($content))
		{
			$content = static::$transformer->transformResponse($content);
		}

		$formatter = static::getFormatter($format);

		// Set the "Content-Type" header of the response to that which
		// is defined by the formatter being used.
		$contentType = $this->headers->get('content-type');

		$this->headers->set('content-type', $formatter->getContentType());

		if ($content instanceof EloquentModel)
		{
			$content = $formatter->formatEloquentModel($content);
		}
		elseif ($content instanceof EloquentCollection)
		{
			$content = $formatter->formatEloquentCollection($content);
		}
		elseif (is_array($content) or $content instanceof ArrayableInterface)
		{
			$content = $formatter->formatArray($content);
		}
		else
		{
			$content = $formatter->formatOther($content);

			$this->headers->set('content-type', $contentType);
		}

		// Directly set the property because using setContent results in
		// the original content also being updated.
		$this->content = $content;

		return $this;
	}

	/**
	 * Get the formatter based on the requested format type.
	 * 
	 * @param  string  $format
	 * @return \Dingo\Api\Http\Format\FormatInterface
	 * @throws \RuntimeException
	 */
	public static function getFormatter($format)
	{
		if ( ! isset(static::$formatters[$format]))
		{
			throw new RuntimeException('Response formatter "'.$format.'" has not been registered.');
		}

		return static::$formatters[$format];
	}

	/**
	 * Set the response formatters.
	 * 
	 * @param  array  $formatters
	 * @return void
	 */
	public static function setFormatters(array $formatters)
	{
		static::$formatters = $formatters;
	}

	/**
	 * Set the transformer factory instance.
	 * 
	 * @param  \Dingo\Api\Transformer\Factory  $transformer
	 * @return void
	 */
	public static function setTransformer(Factory $transformer)
	{
		static::$transformer = $transformer;
	}

	/**
	 * Get the transformer factory instance.
	 * 
	 * @return \Dingo\Api\Transformer\Factory
	 */
	public static function getTransformer()
	{
		return static::$transformer;
	}

}
