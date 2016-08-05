<?php

use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Validator\Uniqueness;

class distributions extends Model {

    /**
     * @var integer
     */
    protected $distributionid;

    /**
     * @var string
     */
    protected $distributrionname;

    /**
     * @var string
     */
    protected $distributionurl;

    /**
     * @var string
     */
    protected $filename;
    
    public function initialize()
    {
        $this->setSource("epm_drupal_distributions");
    }


    public function validation()
    {
        $this->validate(new Uniqueness(array(
            'field' => 'distributionid'
        )));
        if ($this->validationHasFailed() == true) {
            return false;
        }

        return true;
    }

    public function setDistribution($distributionid, $distributionname, $distributionurl, $filename)
    {
        $this->distributionid = $distributionid;
        $this->distributionname = $distributionname;
        $this->distributionurl = $distributionurl;
        if ($this->validation()) {
            $this->save();
        }

        return $this->distributionid;
    }

    public function updateDistribution($distributionid, $distributionname, $distributionurl, $filename)
    {
        $distribution_id = $this->distributionid;
        $this->distributionname = $distributionname;
        $this->distributionurl = $distributionurl;
        $this->filename = $filename;
        if ($this->validation() && ($distribution_id == $distributionid)) {
            $this->save();
        } else {
            return null;
        }

        return $this->distributionid;
    }

    public function getDistributionurl($distributionname)
    {
        $robot = distributions::findFirst("distributionname='$distributionname'");
        If ($robot!= null) {
            $disturl = $robot->distributionurl;
            return $disturl;
        } else {
            return null;
        }
    }
}
?>
