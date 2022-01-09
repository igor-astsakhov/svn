<?php

namespace iaskakho\svn;

class Svn
{
    protected \DirectoryIterator $_di;
    protected \ArrayIterator $_error;
    protected \ArrayIterator $_todo;
    protected string $_dir;
    // public array $arrLines = [];
    protected array $_keys = [ 'todo', 'error' ];

    /**
     * Svn Construct class helper things for the svn repos
     *
     * @param string $strDir - directory where the svn repos are located,
     *                          this is the base dir for all of the repose.
     * @package ia\bin
     * @subpackage svn
     * @author igor.astakhov <astahov@gmail.com>
     */
    public function __construct( string $strDir = '/home/mirror' )
    {
        $this->_dir = $strDir;
        $this->_di = new \DirectoryIterator( $this->_dir );
        $this->_error = new \ArrayIterator();
        $this->_todo = new \ArrayIterator();
    } // END public class __construct( $strDir )

    /**
     * Checks the SVN repo status and returns the output, so you dont have to go
     * into each one and do it in future we can do add actions and others
     *
     * @param bool $boolVerbose - should the output be verbose or short
     * @return void
     */
    public function status( bool $boolVerbose = false ) : void
    {
        foreach ( $this->_di as $objFile ) {
            if ( $objFile->isDot() || ! $this->_di->isDir()) {
                continue;
            }

            $strCmd = 'svn st ' . $this->_dir . '/' . $this->_di->getFilename() . ' 2>&1';

            exec( $strCmd, $arrLines );
            $intTotal = count( $arrLines );
            if ( ! $intTotal ) {
                continue;
            }

            if ( substr( $arrLines[0], 0, 3 ) === 'svn' ) {
                // error on repo
                $this->_error->offsetSet( $this->_di->key(),
                    $this->_di->getFilename() . ' -> ' . $arrLines[0] );
                $arrLines = [];
                continue;
            }

            $this->_todo->offsetSet(
                $this->_di->key(),
                // if verbose to draw the files
                $objFile->getFilename() . ' - '
                . ( $boolVerbose ? PHP_EOL . implode( "\t\n", $arrLines ) : $intTotal )
            );
            $arrLines = [];
        }
        $this->_out();
    }

    /**
     * Removed rpms from the svn dirs
     *
     * @param bool $boolDryRun - dry run without rm
     * @return void
     * @author igor.astakhov <astahov@gmail.com>
     */
    public function rmrpm( bool $boolDryRun = false )
    {
        foreach ( $this->_di as $objFile ) {
            if ( $objFile->isDot() || ! $this->_di->isDir()) {
                continue;
            }

            $strDir = $this->_dir . '/' . $objFile->getFilename() . '/pkg/*.rpm';
            $objPkg = new \GlobIterator( $strDir );
            if ( ! $objPkg->count() ) {
                $this->_error->offsetSet(
                    $this->_di->key(),
                    $strDir . ' = 0'
                );
                continue;
            }

            $strLine = $objFile->getFilename() . ' (' . $objPkg->count() . '):' . PHP_EOL;
            foreach ( $objPkg as $objPkgFile ) {
                $strLine .= "\t" . $objPkgFile->getFilename() . ' [-]' . PHP_EOL;

                if ( ! $boolDryRun ) {
                    // $strLine .= "\tRemoved: " . $objPkgFile->getRealPath() . ' [x]' . PHP_EOL;
                    unlink( $objPkgFile->getRealPath() );
                }
            }
            $this->_todo->offsetSet(
                $this->_di->key(),
                $strLine
            );
        }
        $this->_out();
    }

    /**
     * send the output of the app
     */
    protected function _out()
    {
        foreach ( $this->_keys as $strName ) {
            $objIterator = $this->{'_' . $strName };
            if ( $objIterator->count() ) {
                $arrLines = [
                    'Repos ' . ucwords( str_replace( '_', ' ',  $strName ) ) . ': ',
                    str_repeat( '-', 10 ),
                    ...$objIterator->getArrayCopy(),
                    str_repeat( '-', 10 ) . PHP_EOL,
                ];
                echo implode( PHP_EOL, $arrLines ) . PHP_EOL ;
            }
        }
    }
}
