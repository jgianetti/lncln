<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;

interface UserRepositoryInterface
{
    public function save(array $user);
    public function del($id);
}