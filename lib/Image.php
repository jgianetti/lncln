<?php
/**
 * Modified by Janux - 30.09.11
 * 
 * $image = new Image();
 * $image->load($_FILES['uploaded_image']['tmp_name']);
*/
class Image
{
	var $image;
    /**
     * @var int
     */
	var $image_type;

	public function __construct($filename = null)
	{
		if (isset($filename))
			$this->load($filename);
	}

    /**
     * @param string $filename
     */
	function load($filename)
	{
		$image_info = getimagesize($filename);
		$this->image_type = $image_info[2];
		if ($this->image_type == IMAGETYPE_JPEG) {
			$this->image = imagecreatefromjpeg($filename);
		} elseif ($this->image_type == IMAGETYPE_GIF) {
			$this->image = imagecreatefromgif($filename);
		} elseif ($this->image_type == IMAGETYPE_PNG) {
			$this->image = imagecreatefrompng($filename);
		}
	}

    /**
     * @param string $filename
     * @param int $image_type
     * @param int $compression
     * @param string $permissions
     */
	function save($filename, $image_type = IMAGETYPE_JPEG, $compression = 75, $permissions = null)
	{
		if ($image_type == IMAGETYPE_JPEG) {
			imagejpeg($this->image, $filename, $compression);
		} elseif ($image_type == IMAGETYPE_GIF) {
			imagegif($this->image, $filename);
		} elseif ($image_type == IMAGETYPE_PNG) {
			$pngQuality = round(abs(($compression - 100) / 11.111111));
			imagepng($this->image, $filename, $pngQuality);
		}
		if ($permissions != null) {
			chmod($filename, $permissions);
		}
	}

    /**
     * @param int $image_type
     */
	function output($image_type = IMAGETYPE_JPEG)
	{
		if ($image_type == IMAGETYPE_JPEG) {
			imagejpeg($this->image);
		} elseif ($image_type == IMAGETYPE_GIF) {
			imagegif($this->image);
		} elseif ($image_type == IMAGETYPE_PNG) {
			imagepng($this->image);
		}
	}

    /**
     * @return int
     */
	function getWidth()
	{
		return imagesx($this->image);
	}

    /**
     * @return int
     */
	function getHeight()
	{
		return imagesy($this->image);
	}

    /**
     * @param int $height
     */
	function resizeToHeight($height)
	{
		$ratio = $height / $this->getHeight();
		$width = $this->getWidth() * $ratio;
		$this->resize($width, $height);
	}

    /**
     * @param int $width
     */
	function resizeToWidth($width)
	{
		$ratio = $width / $this->getWidth();
		$height = $this->getheight() * $ratio;
		$this->resize($width, $height);
	}

    /**
     * @param int $t1
     * @param int $t2
     */
	function scaleToTarget($t1, $t2 = null)
	{
		if (!isset($t2)) $t2 = $t1;
		if (($my_width = $this->getWidth()) > ($my_height = $this->getHeight())) $ratio = $t1 / $my_width;
		else $ratio = $t2 / $my_height;
		$this->resize($my_width*$ratio, $my_height*$ratio);
	}

    /**
     * @param int $scale
     */
	function scale($scale)
	{
		$width = $this->getWidth() * $scale / 100;
		$height = $this->getheight() * $scale / 100;
		$this->resize($width, $height);
	}

    /**
     * @param int $width
     * @param int $height
     */
	function resize($width, $height)
	{
		$new_image = imagecreatetruecolor($width, $height);
		imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		$this->image = $new_image;
	}
}

?>