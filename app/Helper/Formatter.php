<?php

namespace App\Helper;

class Formatter
{
    public static function response($code, $message, $data = null, $error = null)
    {
        $result = [
            "message" => $message,
        ];

        if ($error !== null) {
            $result = array_merge($result, ["description" => $error]);
        }

        if ($data !== null) {
            $result = array_merge($result, ["data" => $data['data']]);
        }

        if (isset($data['meta'])) {
            $result = array_merge($result, ["meta" => $data['meta']]);
        }

        return response($result, $code);
    }

    public static function dataWithPagination($data, $page, $limit, $totalData, $totalPage)
    {
        return [
            "data" => [...$data],
            "meta" => [
                "page" => $page,
                "limit" => $limit,
                "totalData" => $totalData,
                "totalPage" => $totalPage,
            ]
        ];
    }
}
