<?php

namespace Erelke\TwigSpreadsheetBundle\Twig\NodeVisitor;

use Erelke\TwigSpreadsheetBundle\Twig\Node\BaseNode;
use Erelke\TwigSpreadsheetBundle\Twig\Node\DocumentNode;
use function get_class;
use Twig\NodeVisitor\AbstractNodeVisitor as Twig_BaseNodeVisitor;
use Twig\Environment as Twig_Environment;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Node\Node as Twig_Node;
use Twig\Node\TextNode as Twig_Node_Text;

/**
 * Class SyntaxCheckNodeVisitor.
 */
class SyntaxCheckNodeVisitor extends Twig_BaseNodeVisitor
{
    /**
     * @var array
     */
    protected $path = [];

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Twig_Error_Syntax
     */
    protected function doEnterNode(Twig_Node $node, Twig_Environment $env): Twig_Node
    {
        try {
            if ($node instanceof BaseNode) {
                $this->checkAllowedParents($node);
            } else {
                $this->checkAllowedChildren($node);
            }
        } catch (Twig_Error_Syntax $e) {
            // reset path since throwing an error prevents doLeaveNode to be called
            $this->path = [];
            throw $e;
        }

        $this->path[] = $node !== null ? get_class($node) : null;

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(Twig_Node $node, Twig_Environment $env): ?Twig_Node
    {
        array_pop($this->path);

        return $node;
    }

    /**
     * @param Twig_Node $node
     *
     * @throws Twig_Error_Syntax
     */
    private function checkAllowedChildren(Twig_Node $node)
    {
        $hasDocumentNode = false;
        $hasTextNode = false;

        /**
         * @var Twig_Node $currentNode
         */
        foreach ($node->getIterator() as $currentNode) {
            if ($currentNode instanceof Twig_Node_Text) {
                if ($hasDocumentNode) {
                    throw new Twig_Error_Syntax(sprintf('Node "%s" is not allowed after Node "%s".', Twig_Node_Text::class, DocumentNode::class));
                }
                $hasTextNode = true;
            } elseif ($currentNode instanceof DocumentNode) {
                if ($hasTextNode) {
                    throw new Twig_Error_Syntax(sprintf('Node "%s" is not allowed before Node "%s".', Twig_Node_Text::class, DocumentNode::class));
                }
                $hasDocumentNode = true;
            }
        }
    }

    /**
     * @param BaseNode $node
     *
     * @throws Twig_Error_Syntax
     */
    private function checkAllowedParents(BaseNode $node)
    {
        $parentName = null;

        // find first parent from this bundle
        foreach (array_reverse($this->path) as $className) {
            if (strpos($className, 'Erelke\\TwigSpreadsheetBundle\\Twig\\Node\\') === 0) {
                $parentName = $className;
                break;
            }
        }

        // allow no parents (e.g. macros, includes)
        if ($parentName === null) {
            return;
        }

        // check if parent is allowed
        foreach ($node->getAllowedParents() as $className) {
            if ($className === $parentName) {
                return;
            }
        }

        throw new Twig_Error_Syntax(sprintf('Node "%s" is not allowed inside of Node "%s".', get_class($node), $parentName));
    }
}