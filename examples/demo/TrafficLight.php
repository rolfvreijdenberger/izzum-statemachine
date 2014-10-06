<?php
namespace izzum\examples\demo;
/**
 * Traffic light is the domain object of our example.
 * 
 * It has a unique identifier, the color it is currently on and the time since
 * the last switch.
 * 
 * - the Rule will query it for information (isReadyToSwitch)
 * - the Commands will manipulate the object to set it's color
 */
class TrafficLight {
    //the id
    private $id;
    //possible color
    private $color;
    //the time since the last switch
    private $switch_time;
    //define the times allocated for each color to be on
    const TIME_RED = 4,
          TIME_ORANGE = 2,
          TIME_GREEN = 6;
    
    public function __construct($id) {
        $this->setId($id);
        $this->setGreen();
    }
    
    protected function setId($id) {
        $this->id = $id;
    }
    
    protected function setSwitchTime()
    {
        $this->switch_time = time();
    }
    
    public function setGreen() {
        $this->setColor('green');
    }
    
    public function setRed() {
        $this->setColor('red');
    }
    
    public function setOrange()
    {
        $this->setColor('orange');
    }
    
    protected function setColor($color) {
        $this->setSwitchTime();
        $this->color = $color;
        echo sprintf('trafficlight[%s] switching to [%s]', 
                $this->id, strtoupper($color)) . PHP_EOL;
    }
    
    public function isReadyToSwitch() {
        $output = false;
        switch ($this->color) {
            case 'red':
                if($this->onColorFor(self::TIME_RED))
                {
                    $output = true;
                }
                break;
            case 'green':
                if($this->onColorFor(self::TIME_GREEN))
                {
                    $output = true;
                }
                break;
            case 'orange':
                if($this->onColorFor(self::TIME_ORANGE))
                {
                    $output = true;
                }
                break;
        }
        return $output;
    }
    
    protected function onColorFor($time) {
        $difference = $this->switch_time + $time;
        if(time() >= $difference) {
            return true;
        }
        return false;
    }
    
    public function toString() {
        return sprintf("trafficlight[%s] on color [%s] for [%s] seconds", 
                $this->id, $this->color, time() - $this->switch_time) .
            PHP_EOL;
    }  
}

