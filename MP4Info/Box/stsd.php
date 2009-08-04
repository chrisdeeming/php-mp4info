<?php
/**
 * MP4Info
 * 
 * @author 		Tommy Lacroix <lacroix.tommy@gmail.com>
 * @copyright   Copyright (c) 2006-2009 Tommy Lacroix
 * @license		LGPL version 3, http://www.gnu.org/licenses/lgpl.html
 * @package 	php-mp4info
 * @link 		$HeadURL$
 */

// ---

/**
 * 8.16 Sample Description Box
 * 
 * @author 		Tommy Lacroix <lacroix.tommy@gmail.com>
 * @version 	1.0.20090601	$Id$
 * @todo 		Factor this into a fullbox
 */
class MP4Info_Box_stsd extends MP4Info_Box {
	/**
	 * Version
	 *
	 * @var int
	 */
	protected $version;
	
	/**
	 * Flags
	 *
	 * @var int
	 */
	protected $flags;
	
	/**
	 * Count
	 *
	 * @var int
	 */
	protected $count;
	
	/**
	 * Values
	 *
	 * @var string{}
	 */
	protected $values = array();
	
	
	/**
	 * Constructor
	 *
	 * @author 	Tommy Lacroix <lacroix.tommy@gmail.com>
	 * @param	int					$totalSize
	 * @param	int					$boxType
	 * @param	file|string			$data
	 * @param	MP4Info_Box			$parent
	 * @return 	MP4Info_Box_stsd
	 * @access 	public
	 * @throws 	MP4Info_Exception
	 */		
	public function __construct($totalSize, $boxType, $data, $parent) {
		if (!self::isCompatible($boxType, $parent)) {
			throw new Exception('This box isn\'t "stsd"');
		}

		// Call ancestor
		parent::__construct($totalSize,$boxType,'',$parent);
		
		// Get data
		$data = self::getDataFrom3rd($data, $totalSize);

		// Unpack
		$ar = unpack('Cversion/C3flags/Ncount',$data);
		$this->version = $ar['version'];
		$this->flags = $ar['flags1']*65536+$ar['flags1']*256+$ar['flags1']*1;
		$this->count = $ar['count'];
		// Recurse to mdia...
		$cursor = $parent;
		while (($cursor !== false) && ($cursor->getBoxTypeStr() != 'mdia')) {
			$cursor = $cursor->getParent();
		}
		if ($cursor !== false) {
			// Then to hdlr
			$this->parent_mdia = $cursor;
			unset($cursor);
			foreach ($this->parent_mdia->children() as $cursor) {
				if ($cursor->getBoxTypeStr() == 'hdlr') {
					$this->parent_hdlr = $cursor;
					$this->type = $cursor->getHandlerType();
				}
			}
		}
		
		// Unpack SAMPLEDESCRIPTION
		$desc = substr($data,8);
		for ($i=0;$i<$this->count;$i++) {
			$ar = unpack('Nlen',$desc);
			$type = substr($desc,4,4);
			$rawinfo = substr($desc,8,$ar['len']-8);
			
			print $type.PHP_EOL;
			switch ($type) {
				case 'avc1':
				case 'mp4v':
				case 'h264':
				case 'H264':
					$this->type = MP4Info_Box_hdlr::HANDLER_VIDEO;
					break;
				case '.mp3':
					$this->type = MP4Info_Box_hdlr::HANDLER_SOUND;
					break;
				case 'mp4s':
				case 'mp4a':
					// Get the esds atom
					/*if ((strpos($rawinfo,'esds') !== false)) {
						$esds = substr($rawinfo,strpos($rawinfo,'esds')+4);
						print $esds;
						$esdsInfo = unpack('Nversion/nflags/N/n/nsample/n8',$esds);
						print_r($esdsInfo);
						print decbin($esdsInfo['flags']);
						die('YAY!');
					}
					
					print $rawinfo;
					//$esds = MP4Info_Box::fromString(substr($rawinfo,8), $this);
					die();*/
					break;
			}
			
			// Try to decode info
			switch ($this->type) {
				case MP4Info_Box_hdlr::HANDLER_VIDEO: 
					$ar2 = unpack('N6dummy0/nwidth/nheight/Nhres/Nvres/Ndummy3/nframeCount/CstrLen/a31compressor/ndepth/ndummy4',$rawinfo);
					//print_r($ar2);
					$info = array(
						'width'=>$ar2['width'],
						'height'=>$ar2['height'],
						'compressor'=>$ar2['compressor'],
					);
					break;
				case MP4Info_Box_hdlr::HANDLER_SOUND: 
				case MP4Info_Box_hdlr::HANDLER_SOUND_ODSM: 
					print bin2hex($rawinfo);
					print '<br>';
					print $rawinfo;
					print '<br>';
					print $type;
					$ar2 = unpack('Ndummy0/ndummy1/nchannelCount/nsampleSize/ndummy3/NsampleRate',$rawinfo);
					//$ar2['sampleRate'] /= 65536;
					
					foreach ($ar2 as $k=>$v) {
						print $k.'='.$v.' .. (0x'.dechex($v).')'.PHP_EOL;
					}
					print_r($ar2);
					die();
					break;
				default:
					$info = $rawinfo;
			}
			
			$this->values[$type] = $info;
		}
	} // Constructor
	
	
	/**
	 * Check if block is compatible with class
	 *
	 * @author 	Tommy Lacroix <lacroix.tommy@gmail.com>
	 * @param 	int					$boxType
	 * @param 	MP4Info_Box			$parent
	 * @return 	bool
	 * @access 	public
	 * @static
	 */			
	static public function isCompatible($boxType, $parent) {
		return $boxType == 0x73747364;
	} // isCompatible method
	
	/**
	 * Values getter
	 *
	 * @author 	Tommy Lacroix <lacroix.tommy@gmail.com>
	 * @return string{}
	 * @access 	public
	 */
	public function getValues() {
		return $this->values;
	} // getValues method
	
	/**
	 * Value getter
	 *
	 * @author 	Tommy Lacroix <lacroix.tommy@gmail.com>
	 * @param	string $key
	 * @return	string
	 * @access 	public
	 */
	public function getValue($key) {
		return isset($this->values[$key]) ? $this->values[$key] : false;
	} // getValue method
	
	/**
	 * String converter
	 *
	 * @author 	Tommy Lacroix <lacroix.tommy@gmail.com>
	 * @return	string
	 * @access 	public
	 */		
	public function toString() {
		return '[MP4Info_Box_stsd:'.implode(',',array_keys($this->values)).']';
	} // toString method
} // MP4Info_Box_stsd class