<?php

namespace iio\libmergepdf;

use Symfony\Component\Finder\Finder;
use FPDI;

/**
 * Merge existing pdfs into one
 */
class Merger
{
    /**
     * Array of files to be merged.
     *
     * Values for each files are filename, Pages object and a boolean value
     * indicating if the file should be deleted after merging is complete.
     *
     * @var array
     */
    private $files = array();

    /**
     * @var FPDI
     */
    private $fpdi;

    /**
     * @var string Directory path used for temporary files
     */
    private $tempDir;

    /**
     * @param FPDi $fpdi
     */
    public function __construct(FPDI $fpdi = null)
    {
        $this->fpdi = $fpdi ?: new FPDI;
    }

    /**
     * Add raw PDF from string
     *
     * Note that your PDFs are merged in the order that you add them
     *
     * @param  string    $pdf
     * @param  Pages     $pages
     * @return void
     * @throws Exception if unable to create temporary file
     */
    public function addRaw($pdf, Pages $pages = null)
    {
        assert('is_string($pdf)');

        // Create temporary file
        $fname = $this->getTempFname();
        if (@file_put_contents($fname, $pdf) === false) {
            throw new Exception("Unable to create temporary file");
        }

        $this->addFromFile($fname, $pages, true);
    }

    /**
     * Add PDF from filesystem path
     *
     * Note that your PDFs are merged in the order that you add them
     *
     * @param  string    $fname   Name of file to add
     * @param  Pages     $pages   Pages to add from file
     * @param  bool      $cleanup Flag if file should be deleted after merging
     * @return void
     * @throws Exception If $fname is not a valid file
     */
    public function addFromFile($fname, Pages $pages = null, $cleanup = false)
    {
        assert('is_string($fname)');
        assert('is_bool($cleanup)');

        if (!is_file($fname) || !is_readable($fname)) {
            throw new Exception("'$fname' is not a valid file");
        }

        if (!$pages) {
            $pages = new Pages();
        }

        $this->files[] = array($fname, $pages, $cleanup);
    }

    /**
     * Add files using iterator
     *
     * @param  array|\Traversable $iterator
     * @return void
     * @throws Exception If $iterator is not valid
     */
    public function addIterator($iterator)
    {
        if (!is_array($iterator) && !$iterator instanceof \Traversable) {
            throw new Exception("\$iterator must be traversable");
        }

        foreach ($iterator as $fname) {
            $this->addFromFile($fname);
        }
    }

    /**
     * Add files using symfony finder
     *
     * @param  Finder $finder
     * @return void
     */
    public function addFinder(Finder $finder)
    {
        foreach ($finder as $fileInfo) {
            $this->addFromFile($fileInfo->getRealpath());
        }
    }

    /**
     * Merges your provided PDFs and get a raw string
     *
     * @return string
     */
    public function merge()
    {
        $fpdi = clone $this->fpdi;
        $fname = '';

        try {
            while ($fileData = array_pop($this->files)) {
                list($fname, $pages, $cleanup) = $fileData;

                $pages = $pages->getPages();
                $iPageCount = $this->fpdi->setSourceFile($fname);

                // If no pages are specified, add all pages
                if (empty($pages)) {
                    $pages = range(1, $iPageCount);
                }

                foreach ($pages as $page) {
                    $template = $this->fpdi->importPage($page);
                    $size = $this->fpdi->getTemplateSize($template);
                    $orientation = ($size['w'] > $size['h']) ? 'L' : 'P';
                    $this->fpdi->AddPage($orientation, array($size['w'], $size['h']));
                    $this->fpdi->useTemplate($template);
                }

                if ($cleanup) {
                    unlink($fname);
                }
            }

            return $fpdi->Output('', 'S');
        } catch (\Exception $e) {
            throw new Exception("FPDI: '{$e->getMessage()}' in '$fname'", 0, $e);
        }
    }

    /**
     * Create temporary file and return name
     *
     * @return string
     */
    public function getTempFname()
    {
        return tempnam($this->getTempDir(), "libmergepdf");
    }

    /**
     * Get directory path for temporary files
     *
     * Set path using setTempDir(), defaults to sys_get_temp_dir().
     *
     * @return string
     */
    public function getTempDir()
    {
        return $this->tempDir ?: sys_get_temp_dir();
    }

    /**
     * Set directory path for temporary files
     *
     * @param  string $dirname
     * @return void
     */
    public function setTempDir($dirname)
    {
        $this->tempDir = $dirname;
    }
}
