<?php
namespace App\Managers;

class Data
{
    /**
     * @param string $scope
     * @return \Octo\Fillable
     */
    public static function get(string $scope = 'app')
    {
        return store('data.' . $scope);
    }
}
