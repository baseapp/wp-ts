<?php

namespace splitbrain\PHPArchive;
/**
 * Class FileInfo
 *
 * stores meta data about a file in an Archive
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @package splitbrain\PHPArchive
 * @license MIT
 */
class FileInfo
{

    protected $isdir = false;
    protected $path = '';
    protected $size = 0;
    protected $csize = 0;
    protected $mtime = 0;
    protected $mode = 0664;
    protected $owner = '';
    protected $group = '';
    protected $uid = 0;
    protected $gid = 0;
    protected $comment = '';

    /**
     * initialize dynamic defaults
     *
     * @param string $path The path of the file, can also be set later through setPath()
     */
    public function __construct($path = '')
    {
        $this->mtime = time();
        $this->setPath($path);
    }

    /**
     * Factory to build FileInfo from existing file or directory
     *
     * @param string $path path to a file on the local file system
     * @param string $as   optional path to use inside the archive
     * @throws FileInfoException
     * @return FileInfo
     */
    public static function fromPath($path, $as = '')
    {
        clearstatcache(false, $path);

        if (!file_exists($path)) {
            throw new FileInfoException("$path does not exist");
        }

        $stat = stat($path);
        $file = new FileInfo();

        $file->setPath($path);
        $file->setIsdir(is_dir($path));
        $file->setMode(fileperms($path));
        $file->setOwner(fileowner($path));
        $file->setGroup(filegroup($path));
        $file->setSize(filesize($path));
        $file->setUid($stat['uid']);
        $file->setGid($stat['gid']);
        $file->setMtime($stat['mtime']);

        if ($as) {
            $file->setPath($as);
        }

        return $file;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getCompressedSize()
    {
        return $this->csize;
    }

    /**
     * @param int $csize
     */
    public function setCompressedSize($csize)
    {
        $this->csize = $csize;
    }

    /**
     * @return int
     */
    public function getMtime()
    {
        return $this->mtime;
    }

    /**
     * @param int $mtime
     */
    public function setMtime($mtime)
    {
        $this->mtime = $mtime;
    }

    /**
     * @return int
     */
    public function getGid()
    {
        return $this->gid;
    }

    /**
     * @param int $gid
     */
    public function setGid($gid)
    {
        $this->gid = $gid;
    }

    /**
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param int $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return boolean
     */
    public function getIsdir()
    {
        return $this->isdir;
    }

    /**
     * @param boolean $isdir
     */
    public function setIsdir($isdir)
    {
        // default mode for directories
        if ($isdir && $this->mode === 0664) {
            $this->mode = 0775;
        }
        $this->isdir = $isdir;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param int $mode
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param string $owner
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $this->cleanPath($path);
    }

    /**
     * Cleans up a path and removes relative parts, also strips leading slashes
     *
     * @param string $path
     * @return string
     */
    protected function cleanPath($path)
    {
        $path    = str_replace('\\', '/', $path);
        $path    = explode('/', $path);
        $newpath = array();
        foreach ($path as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                array_pop($newpath);
                continue;
            }
            array_push($newpath, $p);
        }
        return trim(implode('/', $newpath), '/');
    }

    /**
     * Strip given prefix or number of path segments from the filename
     *
     * The $strip parameter allows you to strip a certain number of path components from the filenames
     * found in the tar file, similar to the --strip-components feature of GNU tar. This is triggered when
     * an integer is passed as $strip.
     * Alternatively a fixed string prefix may be passed in $strip. If the filename matches this prefix,
     * the prefix will be stripped. It is recommended to give prefixes with a trailing slash.
     *
     * @param  int|string $strip
     * @return FileInfo
     */
    public function strip($strip)
    {
        $filename = $this->getPath();
        $striplen = strlen($strip);
        if (is_int($strip)) {
            // if $strip is an integer we strip this many path components
            $parts = explode('/', $filename);
            if (!$this->getIsdir()) {
                $base = array_pop($parts); // keep filename itself
            } else {
                $base = '';
            }
            $filename = join('/', array_slice($parts, $strip));
            if ($base) {
                $filename .= "/$base";
            }
        } else {
            // if strip is a string, we strip a prefix here
            if (substr($filename, 0, $striplen) == $strip) {
                $filename = substr($filename, $striplen);
            }
        }

        $this->setPath($filename);
    }

    /**
     * Does the file match the given include and exclude expressions?
     *
     * Exclude rules take precedence over include rules
     *
     * @param string $include Regular expression of files to include
     * @param string $exclude Regular expression of files to exclude
     * @return bool
     */
    public function match($include = '', $exclude = '')
    {
        $extract = true;
        if ($include && !preg_match($include, $this->getPath())) {
            $extract = false;
        }
        if ($exclude && preg_match($exclude, $this->getPath())) {
            $extract = false;
        }

        return $extract;
    }
}

class FileInfoException extends \Exception
{
}

abstract class Archive
{

    const COMPRESS_AUTO = -1;
    const COMPRESS_NONE = 0;
    const COMPRESS_GZIP = 1;
    const COMPRESS_BZIP = 2;

    /**
     * Set the compression level and type
     *
     * @param int $level Compression level (0 to 9)
     * @param int $type  Type of compression to use (use COMPRESS_* constants)
     * @return mixed
     */
    abstract public function setCompression($level = 9, $type = Archive::COMPRESS_AUTO);

    /**
     * Open an existing archive file for reading
     *
     * @param string $file
     * @throws ArchiveIOException
     */
    abstract public function open($file);

    /**
     * Read the contents of an archive
     *
     * This function lists the files stored in the archive, and returns an indexed array of FileInfo objects
     *
     * The archive is closed afer reading the contents, because rewinding is not possible in bzip2 streams.
     * Reopen the file with open() again if you want to do additional operations
     *
     * @return FileInfo[]
     */
    abstract public function contents();

    /**
     * Extract an existing archive
     *
     * The $strip parameter allows you to strip a certain number of path components from the filenames
     * found in the archive file, similar to the --strip-components feature of GNU tar. This is triggered when
     * an integer is passed as $strip.
     * Alternatively a fixed string prefix may be passed in $strip. If the filename matches this prefix,
     * the prefix will be stripped. It is recommended to give prefixes with a trailing slash.
     *
     * By default this will extract all files found in the archive. You can restrict the output using the $include
     * and $exclude parameter. Both expect a full regular expression (including delimiters and modifiers). If
     * $include is set, only files that match this expression will be extracted. Files that match the $exclude
     * expression will never be extracted. Both parameters can be used in combination. Expressions are matched against
     * stripped filenames as described above.
     *
     * The archive is closed afterwards. Reopen the file with open() again if you want to do additional operations
     *
     * @param string     $outdir  the target directory for extracting
     * @param int|string $strip   either the number of path components or a fixed prefix to strip
     * @param string     $exclude a regular expression of files to exclude
     * @param string     $include a regular expression of files to include
     * @throws ArchiveIOException
     * @return array
     */
    abstract public function extract($outdir, $strip = '', $exclude = '', $include = '');

    /**
     * Create a new archive file
     *
     * If $file is empty, the archive file will be created in memory
     *
     * @param string $file
     */
    abstract public function create($file = '');

    /**
     * Add a file to the current archive using an existing file in the filesystem
     *
     * @param string          $file     path to the original file
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data, empty to take from original
     * @throws ArchiveIOException
     */
    abstract public function addFile($file, $fileinfo = '');

    /**
     * Add a file to the current archive using the given $data as content
     *
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data
     * @param string          $data     binary content of the file to add
     * @throws ArchiveIOException
     */
    abstract public function addData($fileinfo, $data);

    /**
     * Close the archive, close all file handles
     *
     * After a call to this function no more data can be added to the archive, for
     * read access no reading is allowed anymore
     */
    abstract public function close();

    /**
     * Returns the created in-memory archive data
     *
     * This implicitly calls close() on the Archive
     */
    abstract public function getArchive();

    /**
     * Save the created in-memory archive data
     *
     * Note: It is more memory effective to specify the filename in the create() function and
     * let the library work on the new file directly.
     *
     * @param string $file
     */
    abstract public function save($file);

}

class ArchiveIOException extends \Exception
{
}

class ArchiveIllegalCompressionException extends \Exception
{
}

class ArchiveCorruptedException extends \Exception
{
}

/**
 * Class Tar
 *
 * Creates or extracts Tar archives. Supports gz and bzip compression
 *
 * Long pathnames (>100 chars) are supported in POSIX ustar and GNU longlink formats.
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @package splitbrain\PHPArchive
 * @license MIT
 */
class Tar extends Archive
{

    protected $file = '';
    protected $comptype = Archive::COMPRESS_AUTO;
    protected $complevel = 9;
    protected $fh;
    protected $memory = '';
    protected $closed = true;
    protected $writeaccess = false;

    /**
     * Sets the compression to use
     *
     * @param int $level Compression level (0 to 9)
     * @param int $type  Type of compression to use (use COMPRESS_* constants)
     * @return mixed
     */
    public function setCompression($level = 9, $type = Archive::COMPRESS_AUTO)
    {
        $this->compressioncheck($type);
        $this->comptype  = $type;
        $this->complevel = $level;
        if($level == 0) $this->comptype = Archive::COMPRESS_NONE;
        if($type == Archive::COMPRESS_NONE) $this->complevel = 0;
    }

    /**
     * Open an existing TAR file for reading
     *
     * @param string $file
     * @throws ArchiveIOException
     */
    public function open($file)
    {
        $this->file = $file;

        // update compression to mach file
        if ($this->comptype == Tar::COMPRESS_AUTO) {
            $this->setCompression($this->complevel, $this->filetype($file));
        }

        // open file handles
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            $this->fh = @gzopen($this->file, 'rb');
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            $this->fh = @bzopen($this->file, 'r');
        } else {
            $this->fh = @fopen($this->file, 'rb');
        }

        if (!$this->fh) {
            throw new ArchiveIOException('Could not open file for reading: '.$this->file);
        }
        $this->closed = false;
    }

    /**
     * Read the contents of a TAR archive
     *
     * This function lists the files stored in the archive
     *
     * The archive is closed afer reading the contents, because rewinding is not possible in bzip2 streams.
     * Reopen the file with open() again if you want to do additional operations
     *
     * @throws ArchiveIOException
     * @returns FileInfo[]
     */
    public function contents()
    {
        if ($this->closed || !$this->file) {
            throw new ArchiveIOException('Can not read from a closed archive');
        }

        $result = array();
        while ($read = $this->readbytes(512)) {
            $header = $this->parseHeader($read);
            if (!is_array($header)) {
                continue;
            }

            $this->skipbytes(ceil($header['size'] / 512) * 512);
            $result[] = $this->header2fileinfo($header);
        }

        $this->close();
        return $result;
    }

    /**
     * Extract an existing TAR archive
     *
     * The $strip parameter allows you to strip a certain number of path components from the filenames
     * found in the tar file, similar to the --strip-components feature of GNU tar. This is triggered when
     * an integer is passed as $strip.
     * Alternatively a fixed string prefix may be passed in $strip. If the filename matches this prefix,
     * the prefix will be stripped. It is recommended to give prefixes with a trailing slash.
     *
     * By default this will extract all files found in the archive. You can restrict the output using the $include
     * and $exclude parameter. Both expect a full regular expression (including delimiters and modifiers). If
     * $include is set only files that match this expression will be extracted. Files that match the $exclude
     * expression will never be extracted. Both parameters can be used in combination. Expressions are matched against
     * stripped filenames as described above.
     *
     * The archive is closed afer reading the contents, because rewinding is not possible in bzip2 streams.
     * Reopen the file with open() again if you want to do additional operations
     *
     * @param string     $outdir  the target directory for extracting
     * @param int|string $strip   either the number of path components or a fixed prefix to strip
     * @param string     $exclude a regular expression of files to exclude
     * @param string     $include a regular expression of files to include
     * @throws ArchiveIOException
     * @return FileInfo[]
     */
    public function extract($outdir, $strip = '', $exclude = '', $include = '')
    {
        if ($this->closed || !$this->file) {
            throw new ArchiveIOException('Can not read from a closed archive');
        }

        $outdir = rtrim($outdir, '/');
        @mkdir($outdir, 0777, true);
        if (!is_dir($outdir)) {
            throw new ArchiveIOException("Could not create directory '$outdir'");
        }

        $extracted = array();
        while ($dat = $this->readbytes(512)) {
            // read the file header
            $header = $this->parseHeader($dat);
            if (!is_array($header)) {
                continue;
            }
            $fileinfo = $this->header2fileinfo($header);

            // apply strip rules
            $fileinfo->strip($strip);

            // skip unwanted files
            if (!strlen($fileinfo->getPath()) || !$fileinfo->match($include, $exclude)) {
                $this->skipbytes(ceil($header['size'] / 512) * 512);
                continue;
            }

            // create output directory
            $output    = $outdir.'/'.$fileinfo->getPath();
            $directory = ($fileinfo->getIsdir()) ? $output : dirname($output);
            @mkdir($directory, 0777, true);

            // extract data
            if (!$fileinfo->getIsdir()) {
                $fp = fopen($output, "wb");
                if (!$fp) {
                    throw new ArchiveIOException('Could not open file for writing: '.$output);
                }

                $size = floor($header['size'] / 512);
                for ($i = 0; $i < $size; $i++) {
                    fwrite($fp, $this->readbytes(512), 512);
                }
                if (($header['size'] % 512) != 0) {
                    fwrite($fp, $this->readbytes(512), $header['size'] % 512);
                }

                fclose($fp);
                touch($output, $fileinfo->getMtime());
                chmod($output, $fileinfo->getMode());
            } else {
                $this->skipbytes(ceil($header['size'] / 512) * 512); // the size is usually 0 for directories
            }

            $extracted[] = $fileinfo;
        }

        $this->close();
        return $extracted;
    }

    /**
     * Create a new TAR file
     *
     * If $file is empty, the tar file will be created in memory
     *
     * @param string $file
     * @throws ArchiveIOException
     */
    public function create($file = '')
    {
        $this->file   = $file;
        $this->memory = '';
        $this->fh     = 0;

        if ($this->file) {
            // determine compression
            if ($this->comptype == Archive::COMPRESS_AUTO) {
                $this->setCompression($this->complevel, $this->filetype($file));
            }

            if ($this->comptype === Archive::COMPRESS_GZIP) {
                $this->fh = @gzopen($this->file, 'wb'.$this->complevel);
            } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
                $this->fh = @bzopen($this->file, 'w');
            } else {
                $this->fh = @fopen($this->file, 'wb');
            }

            if (!$this->fh) {
                throw new ArchiveIOException('Could not open file for writing: '.$this->file);
            }
        }
        $this->writeaccess = true;
        $this->closed      = false;
    }

    /**
     * Add a file to the current TAR archive using an existing file in the filesystem
     *
     * @param string          $file     path to the original file
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data, empty to take from original
     * @throws ArchiveIOException
     */
    public function addFile($file, $fileinfo = '')
    {
        if (is_string($fileinfo)) {
            $fileinfo = FileInfo::fromPath($file, $fileinfo);
        }

        if ($this->closed) {
            throw new ArchiveIOException('Archive has been closed, files can no longer be added');
        }

        if ( is_dir($file) ) {

            $this->writeFileHeader($fileinfo);

        } else {

            $fp = fopen($file, 'rb');
            if (!$fp) {
                throw new ArchiveIOException('Could not open file for reading: ' . $file);
            }

            // create file header
            $this->writeFileHeader($fileinfo);

            // write data
            while (!feof($fp)) {
                $data = fread($fp, 512);
                if ($data === false) {
                    break;
                }
                if ($data === '') {
                    break;
                }
                $packed = pack("a512", $data);
                $this->writebytes($packed);
            }
            fclose($fp);
        }
    }

    /**
     * Add a file to the current TAR archive using the given $data as content
     *
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data
     * @param string          $data     binary content of the file to add
     * @throws ArchiveIOException
     */
    public function addData($fileinfo, $data)
    {
        if (is_string($fileinfo)) {
            $fileinfo = new FileInfo($fileinfo);
        }

        if ($this->closed) {
            throw new ArchiveIOException('Archive has been closed, files can no longer be added');
        }

        $len = strlen($data);
        $fileinfo->setSize($len);
        $this->writeFileHeader($fileinfo);

        for ($s = 0; $s < $len; $s += 512) {
            $this->writebytes(pack("a512", substr($data, $s, 512)));
        }
    }

    /**
     * Add the closing footer to the archive if in write mode, close all file handles
     *
     * After a call to this function no more data can be added to the archive, for
     * read access no reading is allowed anymore
     *
     * "Physically, an archive consists of a series of file entries terminated by an end-of-archive entry, which
     * consists of two 512 blocks of zero bytes"
     *
     * @link http://www.gnu.org/software/tar/manual/html_chapter/tar_8.html#SEC134
     */
    public function close()
    {
        if ($this->closed) {
            return;
        } // we did this already

        // write footer
        if ($this->writeaccess) {
            $this->writebytes(pack("a512", ""));
            $this->writebytes(pack("a512", ""));
        }

        // close file handles
        if ($this->file) {
            if ($this->comptype === Archive::COMPRESS_GZIP) {
                gzclose($this->fh);
            } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
                bzclose($this->fh);
            } else {
                fclose($this->fh);
            }

            $this->file = '';
            $this->fh   = 0;
        }

        $this->writeaccess = false;
        $this->closed      = true;
    }

    /**
     * Returns the created in-memory archive data
     *
     * This implicitly calls close() on the Archive
     */
    public function getArchive()
    {
        $this->close();

        if ($this->comptype === Archive::COMPRESS_AUTO) {
            $this->comptype = Archive::COMPRESS_NONE;
        }

        if ($this->comptype === Archive::COMPRESS_GZIP) {
            return gzcompress($this->memory, $this->complevel);
        }
        if ($this->comptype === Archive::COMPRESS_BZIP) {
            return bzcompress($this->memory);
        }
        return $this->memory;
    }

    /**
     * Save the created in-memory archive data
     *
     * Note: It more memory effective to specify the filename in the create() function and
     * let the library work on the new file directly.
     *
     * @param string $file
     * @throws ArchiveIOException
     */
    public function save($file)
    {
        if ($this->comptype === Archive::COMPRESS_AUTO) {
            $this->setCompression($this->complevel, $this->filetype($file));
        }

        if (!file_put_contents($file, $this->getArchive())) {
            throw new ArchiveIOException('Could not write to file: '.$file);
        }
    }

    /**
     * Read from the open file pointer
     *
     * @param int $length bytes to read
     * @return string
     */
    protected function readbytes($length)
    {
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            return @gzread($this->fh, $length);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            return @bzread($this->fh, $length);
        } else {
            return @fread($this->fh, $length);
        }
    }

    /**
     * Write to the open filepointer or memory
     *
     * @param string $data
     * @throws ArchiveIOException
     * @return int number of bytes written
     */
    protected function writebytes($data)
    {
        if (!$this->file) {
            $this->memory .= $data;
            $written = strlen($data);
        } elseif ($this->comptype === Archive::COMPRESS_GZIP) {
            $written = @gzwrite($this->fh, $data);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            $written = @bzwrite($this->fh, $data);
        } else {
            $written = @fwrite($this->fh, $data);
        }
        if ($written === false) {
            throw new ArchiveIOException('Failed to write to archive stream');
        }
        return $written;
    }

    /**
     * Skip forward in the open file pointer
     *
     * This is basically a wrapper around seek() (and a workaround for bzip2)
     *
     * @param int $bytes seek to this position
     */
    function skipbytes($bytes)
    {
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            @gzseek($this->fh, $bytes, SEEK_CUR);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            // there is no seek in bzip2, we simply read on
            // bzread allows to read a max of 8kb at once
            while($bytes) {
                $toread = min(8192, $bytes);
                @bzread($this->fh, $toread);
                $bytes -= $toread;
            }
        } else {
            @fseek($this->fh, $bytes, SEEK_CUR);
        }
    }

    /**
     * Write the given file metat data as header
     *
     * @param FileInfo $fileinfo
     */
    protected function writeFileHeader(FileInfo $fileinfo)
    {
        $this->writeRawFileHeader(
            $fileinfo->getPath(),
            $fileinfo->getUid(),
            $fileinfo->getGid(),
            $fileinfo->getMode(),
            $fileinfo->getSize(),
            $fileinfo->getMtime(),
            $fileinfo->getIsdir() ? '5' : '0'
        );
    }

    /**
     * Write a file header to the stream
     *
     * @param string $name
     * @param int    $uid
     * @param int    $gid
     * @param int    $perm
     * @param int    $size
     * @param int    $mtime
     * @param string $typeflag Set to '5' for directories
     */
    protected function writeRawFileHeader($name, $uid, $gid, $perm, $size, $mtime, $typeflag = '')
    {
        // handle filename length restrictions
        $prefix  = '';
        $namelen = strlen($name);
        if ($namelen > 100) {
            $file = basename($name);
            $dir  = dirname($name);
            if (strlen($file) > 100 || strlen($dir) > 155) {
                // we're still too large, let's use GNU longlink
                $this->writeRawFileHeader('././@LongLink', 0, 0, 0, $namelen, 0, 'L');
                for ($s = 0; $s < $namelen; $s += 512) {
                    $this->writebytes(pack("a512", substr($name, $s, 512)));
                }
                $name = substr($name, 0, 100); // cut off name
            } else {
                // we're fine when splitting, use POSIX ustar
                $prefix = $dir;
                $name   = $file;
            }
        }

        // values are needed in octal
        $uid   = sprintf("%6s ", decoct($uid));
        $gid   = sprintf("%6s ", decoct($gid));
        $perm  = sprintf("%6s ", decoct($perm));
        $size  = sprintf("%11s ", decoct($size));
        $mtime = sprintf("%11s", decoct($mtime));

        $data_first = pack("a100a8a8a8a12A12", $name, $perm, $uid, $gid, $size, $mtime);
        $data_last  = pack("a1a100a6a2a32a32a8a8a155a12", $typeflag, '', 'ustar', '', '', '', '', '', $prefix, "");

        for ($i = 0, $chks = 0; $i < 148; $i++) {
            $chks += ord($data_first[$i]);
        }

        for ($i = 156, $chks += 256, $j = 0; $i < 512; $i++, $j++) {
            $chks += ord($data_last[$j]);
        }

        $this->writebytes($data_first);

        $chks = pack("a8", sprintf("%6s ", decoct($chks)));
        $this->writebytes($chks.$data_last);
    }

    /**
     * Decode the given tar file header
     *
     * @param string $block a 512 byte block containing the header data
     * @return array|false returns false when this was a null block
     * @throws ArchiveCorruptedException
     */
    protected function parseHeader($block)
    {
        if (!$block || strlen($block) != 512) {
            throw new ArchiveCorruptedException('Unexpected length of header');
        }

        // null byte blocks are ignored
        if(trim($block) === '') return false;

        for ($i = 0, $chks = 0; $i < 148; $i++) {
            $chks += ord($block[$i]);
        }

        for ($i = 156, $chks += 256; $i < 512; $i++) {
            $chks += ord($block[$i]);
        }

        $header = @unpack(
            "a100filename/a8perm/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix",
            $block
        );
        if (!$header) {
            throw new ArchiveCorruptedException('Failed to parse header');
        }

        $return['checksum'] = OctDec(trim($header['checksum']));
        if ($return['checksum'] != $chks) {
            throw new ArchiveCorruptedException('Header does not match it\'s checksum');
        }

        $return['filename'] = trim($header['filename']);
        $return['perm']     = OctDec(trim($header['perm']));
        $return['uid']      = OctDec(trim($header['uid']));
        $return['gid']      = OctDec(trim($header['gid']));
        $return['size']     = OctDec(trim($header['size']));
        $return['mtime']    = OctDec(trim($header['mtime']));
        $return['typeflag'] = $header['typeflag'];
        $return['link']     = trim($header['link']);
        $return['uname']    = trim($header['uname']);
        $return['gname']    = trim($header['gname']);

        // Handle ustar Posix compliant path prefixes
        if (trim($header['prefix'])) {
            $return['filename'] = trim($header['prefix']).'/'.$return['filename'];
        }

        // Handle Long-Link entries from GNU Tar
        if ($return['typeflag'] == 'L') {
            // following data block(s) is the filename
            $filename = trim($this->readbytes(ceil($header['size'] / 512) * 512));
            // next block is the real header
            $block  = $this->readbytes(512);
            $return = $this->parseHeader($block);
            // overwrite the filename
            $return['filename'] = $filename;
        }

        return $return;
    }

    /**
     * Creates a FileInfo object from the given parsed header
     *
     * @param $header
     * @return FileInfo
     */
    protected function header2fileinfo($header)
    {
        $fileinfo = new FileInfo();
        $fileinfo->setPath($header['filename']);
        $fileinfo->setMode($header['perm']);
        $fileinfo->setUid($header['uid']);
        $fileinfo->setGid($header['gid']);
        $fileinfo->setSize($header['size']);
        $fileinfo->setMtime($header['mtime']);
        $fileinfo->setOwner($header['uname']);
        $fileinfo->setGroup($header['gname']);
        $fileinfo->setIsdir((bool) $header['typeflag']);

        return $fileinfo;
    }

    /**
     * Checks if the given compression type is available and throws an exception if not
     *
     * @param $comptype
     * @throws ArchiveIllegalCompressionException
     */
    protected function compressioncheck($comptype)
    {
        if ($comptype === Archive::COMPRESS_GZIP && !function_exists('gzopen')) {
            throw new ArchiveIllegalCompressionException('No gzip support available');
        }

        if ($comptype === Archive::COMPRESS_BZIP && !function_exists('bzopen')) {
            throw new ArchiveIllegalCompressionException('No bzip2 support available');
        }
    }

    /**
     * Guesses the wanted compression from the given file
     *
     * Uses magic bytes for existing files, the file extension otherwise
     *
     * You don't need to call this yourself. It's used when you pass Archive::COMPRESS_AUTO somewhere
     *
     * @param string $file
     * @return int
     */
    public function filetype($file)
    {
        // for existing files, try to read the magic bytes
        if(file_exists($file) && is_readable($file) && filesize($file) > 5) {
            $fh = fopen($file, 'rb');
            if(!$fh) return false;
            $magic = fread($fh, 5);
            fclose($fh);

            if(strpos($magic, "\x42\x5a") === 0) return Archive::COMPRESS_BZIP;
            if(strpos($magic, "\x1f\x8b") === 0) return Archive::COMPRESS_GZIP;
        }

        // otherwise rely on file name
        $file = strtolower($file);
        if (substr($file, -3) == '.gz' || substr($file, -4) == '.tgz') {
            return Archive::COMPRESS_GZIP;
        } elseif (substr($file, -4) == '.bz2' || substr($file, -4) == '.tbz') {
            return Archive::COMPRESS_BZIP;
        }

        return Archive::COMPRESS_NONE;
    }
}