<?php
namespace App\Managers;

class Config
{
    /**
     * @param string $scope
     * @return \Octo\Fillable
     */
    public static function get(string $scope = 'app')
    {
        return bag('config.' . $scope);
    }
}
