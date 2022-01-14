<?php

namespace iaskakho\svn;

/**
 * Svn command wrapper to execute commands on multiple packages
 * at the same time.
 */
class Svn
{
    protected \DirectoryIterator $_di;
    protected \ArrayIterator $_error;
    protected \ArrayIterator $_todo;

    /** @var string $_dir - parent dir */
    protected string $_dir;

    /** @var string $_cwd - directory to work with */
    protected string $_cwd;

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

    protected function _runCmd( string $strCmd, bool $boolPrint = true ) : array
    {
        chdir( $this->_cwd );
        exec( $strCmd . ' 2>&1', $arrResponse );
        if ( $boolPrint ) {
            foreach ( $arrResponse as $strLine ) {
                echo $strLine . PHP_EOL;
            }

            if ( count( $arrResponse ) ) {
                echo PHP_EOL;
            }
        }
        return array_filter( $arrResponse );
    }

    /**
     * Adds files to the repo
     *
     * @return void
     * @author igor-astsakhov <astahov@gmail.com>
     */
    protected function _add( array $arrFiles )
    {
        foreach ( $arrFiles as $strLine ) {
            list( $strFlag, $strFile ) = preg_split( '/\s+/', $strLine );

            if ( $strFlag === '?' ) {
                $this->_runCmd( 'svn add ' . $strFile );
            }
        }
    }
    /**
     * commits the package or packages
     *
     * @return void
     * @author igor-astsakhov <astahov@gmail.com>
     */
    public function commit( string $strPackage = 'all' )
    {
        $this->status( false, false );
        foreach ( $this->_todo as $intKey => $strMessage ) {
            list( $strFile, $intFiles ) = explode( ' - ', $strMessage );
            $this->_cwd = $this->_di->getPath() . '/' . $strFile;

            do {
                echo '>> Commit: ' . $this->_cwd . ' FILES: ' . $intFiles . PHP_EOL;
                $arrFiles = $this->_runCmd( 'svn st' );

                $strResponse = substr( strtolower( trim( readline( 'Confirm Y/N/Q OR a(add)/u(up)? ' ) ) ), 0, 1 );

                switch ( $strResponse ) {
                    case 'y':
                        echo 'Committing: ' . $strFile . PHP_EOL;
                        $this->_runCmd( 'svn ci -m "byticket"' );
                        break;

                    case 'a':
                        echo 'Adding: ' . $strFile . PHP_EOL;
                        $this->_add( $arrFiles );
                        break;

                    case 'u':
                        echo 'Updating: ' . $strFile . PHP_EOL;
                        $this->_update();
                        break;

                    case 'n':
                        $arrFiles = [];
                        break;

                    default:
                        exit;
                }
            } while ( count( $arrFiles ) );
        }
    }

    /**
     * Updates the package or packages
     *
     * @return void
     * @author igor-astsakhov <astahov@gmail.com>
     */
    protected function _update()
    {
        $this->_runCmd( 'svn up' );
    }
    /**
     * Checks the SVN repo status and returns the output, so you dont have to go
     * into each one and do it in future we can do add actions and others
     *
     * @param bool $boolVerbose - should the output be verbose or short
     * @return void
     */
    public function status( bool $boolVerbose = false , bool $boolOut = true ) : void
    {
        foreach ( $this->_di as $objFile ) {
            if ( $objFile->isDot() || ! $objFile->isDir() ) {
                continue;
            }

            $this->_cwd = $this->_di->getPath() . '/' . $this->_di->getFilename();
            $arrLines = $this->_runCmd( 'svn st', false );
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

        if ( $boolOut ) {
            $this->_out();
        }
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
        // }
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
