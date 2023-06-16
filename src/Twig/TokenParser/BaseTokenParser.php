<?php

namespace Erelke\TwigSpreadsheetBundle\Twig\TokenParser;

use function count;
use Exception;
use InvalidArgumentException;
use Twig\Node\Node as Twig_Node;
use Twig\Token as Twig_Token;
use Twig\TokenParser\AbstractTokenParser as Twig_TokenParser;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Node\Expression\AbstractExpression as Twig_Node_Expression;
use Twig\Node\Expression\ArrayExpression as Twig_Node_Expression_Array;

/**
 * Class BaseTokenParser.
 */
abstract class BaseTokenParser extends Twig_TokenParser
{
    /**
     * @var int
     */
    const PARAMETER_TYPE_ARRAY = 0;

    /**
     * @var int
     */
    const PARAMETER_TYPE_VALUE = 1;

    /**
     * @var array
     */
    private $attributes;

    /**
     * BaseTokenParser constructor.
     *
     * @param array $attributes optional attributes for the corresponding node
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * @param Twig_Token $token
     *
     * @return array
     */
    public function configureParameters(Twig_Token $token): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Create a concrete node.
     *
     * @param array $nodes
     * @param int   $lineNo
     *
     * @return Twig_Node
     */
    abstract public function createNode(array $nodes = [], int $lineNo = 0): Twig_Node;

    /**
     * @return bool
     */
    public function hasBody(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function parse(Twig_Token $token): Twig_Node
    {
        // parse parameters
        $nodes = $this->parseParameters($this->configureParameters($token));

        // parse body
        if ($this->hasBody()) {
            $nodes['body'] = $this->parseBody();
        }

        return $this->createNode($nodes, $token->getLine());
    }

    /**
     * @param array $parameterConfiguration
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws Twig_Error_Syntax
     *
     * @return Twig_Node_Expression[]
     */
    private function parseParameters(array $parameterConfiguration = []): array
    {
        // parse expressions
        $expressions = [];
        while (!$this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE)) {
            $expressions[] = $this->parser->getExpressionParser()->parseExpression();
        }

        // end of expressions
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        // map expressions to parameters
        $parameters = [];
        foreach ($parameterConfiguration as $parameterName => $parameterOptions) {
            // try mapping expression
            $expression = reset($expressions);
            if ($expression !== false) {
                switch ($parameterOptions['type']) {
                    case self::PARAMETER_TYPE_ARRAY:
                        // check if expression is valid array
                        $valid = $expression instanceof Twig_Node_Expression_Array;
                        break;
                    case self::PARAMETER_TYPE_VALUE:
                        // check if expression is valid value
                        $valid = !($expression instanceof Twig_Node_Expression_Array);
                        break;
                    default:
                        throw new InvalidArgumentException('Invalid parameter type');
                }

                if ($valid) {
                    // set expression as parameter and remove it from expressions list
                    $parameters[$parameterName] = array_shift($expressions);
                    continue;
                }
            }

            // set default as parameter otherwise or throw exception if default is false
            if ($parameterOptions['default'] === false) {
                throw new Twig_Error_Syntax('A required parameter is missing');
            }
            $parameters[$parameterName] = $parameterOptions['default'];
        }

        if (count($expressions) > 0) {
            throw new Twig_Error_Syntax('Too many parameters');
        }

        return $parameters;
    }

    /**
     * @return Twig_Node
     * @throws Twig_Error_Syntax
     */
    private function parseBody(): Twig_Node
    {
        // parse till matching end tag is found
        $body = $this->parser->subparse(function (Twig_Token $token) { return $token->test('end'.$this->getTag()); }, true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return $body;
    }
}
