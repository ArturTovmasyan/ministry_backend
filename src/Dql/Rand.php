<?php

/**
 * Created by PhpStorm.
 * User: arthurt
 * Date: 1/16/20
 * Time: 11:38 AM
 */
namespace App\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Class Rand
 * @package App\Dql
 */
class Rand extends FunctionNode
{
    /**
     * @param Parser $parser
     * @throws QueryException
     */
    public function parse(Parser $parser):void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker):string
    {
        return 'RAND()';
    }
}