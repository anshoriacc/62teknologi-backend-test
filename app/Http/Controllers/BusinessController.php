<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\Image;
use App\Helper\Formatter;
use App\Helper\Storage;
use App\Helper\Uuid;


class BusinessController extends Controller
{
    public function search(Request $request)
    {
        $id = $request["id"];
        $q = $request["q"];
        $category = $request["category"];
        $location = $request["location"];
        $transaction = $request["transaction"];
        $price = $request["price"];
        $open_now = $request["open_now"];
        $orderby = $request["orderby"] ? $request["orderby"] : "businesses.created_at";
        $sort = $request["sort"] ? $request["sort"] : "ASC";
        $page = $request["page"] ? $request["page"] : 1;
        $limit = $request["limit"] ? $request["limit"] : 10;

        $businesses = Business::select(
            "businesses.id",
            "businesses.name",
            "businesses.alias",
            "businesses.open_now",
            "businesses.url",
            "businesses.review_count",
            "businesses.rating",
            "businesses.transactions",
            "businesses.price",
            "businesses.phone",
            "businesses.distance",
            "businesses.created_at",
            "businesses.updated_at"
        )
            ->leftJoin("categories", "businesses.id", "=", "categories.business_id")
            ->leftJoin("locations", "businesses.id", "=", "locations.business_id")
            ->when(!empty($id), function ($query) use ($id) {
                return $query->where("businesses.id", "=", $id);
            })
            ->when(!empty($q), function ($query) use ($q) {
                return $query->where("businesses.name", "LIKE", "%" . $q . "%");
            })
            ->when(!empty($category), function ($query) use ($category) {
                return $query->where("categories.title", "LIKE", "%" . $category . "%");
            })
            ->when(!empty($location), function ($query) use ($location) {
                return $query->where("locations.address1", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.address2", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.address3", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.city", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.state", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.zip_code", "LIKE", "%" . $location . "%")
                    ->orWhere("locations.country", "LIKE", "%" . $location . "%");
            })
            ->when(!empty($transaction), function ($query) use ($transaction) {
                return $query->whereRaw("JSON_CONTAINS(businesses.transactions, '" . $transaction . "')");
            })
            ->when(!empty($price), function ($query) use ($price) {
                return $query->where("businesses.price", $price);
            })
            ->when(!empty($open_now), function ($query) use ($open_now) {
                if ($open_now === "true") {
                    return $query->where("businesses.open_now", "1");
                } elseif ($open_now === "false") {
                    return $query->where("businesses.open_now", "0");
                }
            })
            ->when(!empty($orderby), function ($query) use ($orderby, $sort) {
                return $query->orderBy($orderby, $sort);
            })
            ->when(!empty($limit), function ($query) use ($limit) {
                return $query->limit($limit);
            })
            ->when(!empty($page), function ($query) use ($page, $limit) {
                return $query->offset(($page - 1) * $limit);
            })
            ->distinct("businesses.id")->get()->toArray();

        $result = [];

        foreach ($businesses as $business) {
            $location = Location::select("address1", "address2", "address3", "city", "state", "latitude", "longitude", "zip_code", "country")
                ->where("business_id", $business["id"])->limit(1)->get()->toArray();
            $images = Image::select("url")->where("business_id", $business["id"])->limit(1)->get()->toArray();
            $images = array_map(function ($image) {
                if ($image["url"]) {
                    return $image["url"];
                }
            }, $images);

            $business["open_now"] = $business["open_now"] === "0" ? false : true;
            $business["transactions"] = $business["transactions"] ? json_decode($business["transactions"]) : [];
            $business["location"] = $location ? $location[0] : (object)[];
            $business["images"] = $images;
            $business["categories"] = Category::select("alias", "title")->where("business_id", $business["id"])->get()->toArray();

            array_push($result, $business);
        }

        $totalData = count($result);
        $totalPage = 1;

        if ($limit) {
            $totalPage = ceil($totalData / $limit);
        }

        return Formatter::response(
            200,
            "Success retrieve data.",
            Formatter::dataWithPagination($result, (int)$page, (int)$limit, (int)$totalData, (int)$totalPage),
            NULL
        );
    }

    public function categories()
    {
        $result = Category::select("title")->distinct()->get()->toArray();

        $result = array_map(function ($data) {
            $data = $data["title"];
            return $data;
        }, $result);

        return Formatter::response(200, "Success retrieve categories.", ["data" => $result], NULL);
    }

    public function add(Request $request)
    {
        $id = Uuid::generateUuid();
        $name = $request->input("name");
        $alias = strtolower(trim(preg_replace("/[^A-Za-z0-9-]+/", "-", $name)));
        $images = $request->file("images");
        $transactions = $request->input("transactions");
        $price = $request->input("price");
        $phone = $request->input("phone");
        $address1 = $request->input("address1");
        $address2 = $request->input("address2") ? $request->input("address2") : NULL;
        $address3 = $request->input("address3") ? $request->input("address3") : NULL;
        $latitude = $request->input("latitude");
        $longitude = $request->input("longitude");
        $city = $request->input("city");
        $state = $request->input("state");
        $country = $request->input("country");
        $zip_code = $request->input("zip_code");
        $categories = $request->input("categories");

        if (
            ($name === NULL) ||
            ($images === NULL) ||
            ($transactions === NULL) ||
            ($price === NULL) ||
            ($phone === NULL) ||
            ($address1 === NULL) ||
            ($latitude === NULL) ||
            ($longitude === NULL) ||
            ($city === NULL) ||
            ($state === NULL) ||
            ($country === NULL) ||
            ($zip_code === NULL) ||
            ($categories === NULL)
        ) {
            return Formatter::response(400, "Error.", NULL, "Fill all required field.");
        }

        $insert = DB::transaction(function () use (
            $id,
            $name,
            $alias,
            $transactions,
            $price,
            $phone,
            $images,
            $address1,
            $address2,
            $address3,
            $latitude,
            $longitude,
            $city,
            $state,
            $country,
            $zip_code,
            $categories
        ) {
            $bodyBusiness = [
                "id" => $id,
                "name" => $name,
                "alias" => $alias,
                "transactions" => $transactions,
                "price" => $price,
                "phone" => $phone,
            ];
            $insertBusiness = Business::create($bodyBusiness);

            $insertImages = [];
            foreach ($images as $image) {
                $storeImage = Storage::storeImage($image);
                $insertImage = Image::create(["business_id" => $id, "url" => Storage::getImageUrl($storeImage)]);
                array_push($insertImages, $insertImage);
            }

            $bodyLocation = [
                "business_id" => $id,
                "address1" => $address1,
                "address2" => $address2,
                "address3" => $address3,
                "latitude" => $latitude,
                "longitude" => $longitude,
                "city" => $city,
                "state" => $state,
                "country" => $country,
                "zip_code" => $zip_code,
            ];
            $insertLocation = Location::create($bodyLocation);

            $insertCategories = [];
            foreach ($categories as $category) {
                $insertCategory = Category::create(["business_id" => $id, "title" => $category, "alias" => strtolower(trim(preg_replace("/[^A-Za-z0-9-]+/", "-", $category)))]);
                array_push($insertCategories, $insertCategory);
            }
            $insertedData = $insertBusiness;
            $insertedData["images"] = $insertImages;
            $insertedData["location"] = $insertLocation;
            $insertedData["categories"] = $insertCategories;

            return $insertedData;
        });

        if (!$insert) {
            return Formatter::response(500, "Error", NULL, "Error insert data.");
        }

        return Formatter::response(200, "Success store data.", ["data" => $insert]);
    }

    public function edit(Request $request, $id)
    {
        $name = $request->input("name");
        $images = $request->file("images");
        $open_now = $request->input("open_now");
        $transactions = $request->input("transactions");
        $price = $request->input("price");
        $phone = $request->input("phone");
        $address1 = $request->input("address1");
        $address2 = $request->input("address2") ? $request->input("address2") : NULL;
        $address3 = $request->input("address3") ? $request->input("address3") : NULL;
        $latitude = $request->input("latitude");
        $longitude = $request->input("longitude");
        $city = $request->input("city");
        $state = $request->input("state");
        $country = $request->input("country");
        $zip_code = $request->input("zip_code");
        $categories = $request->input("categories");

        $updatedColumnBusiness = [];
        $updatedColumnLocation = [];

        $name ? $updatedColumnBusiness["name"] = $name : NULL;
        $name ? $updatedColumnBusiness["alias"] = strtolower(trim(preg_replace("/[^A-Za-z0-9-]+/", "-", $name))) : NULL;
        $open_now ? $updatedColumnBusiness["open_now"] = $open_now : NULL;
        $transactions ? $updatedColumnBusiness["transactions"] = $transactions : NULL;
        $price ? $updatedColumnBusiness["price"] = $price : NULL;
        $phone ? $updatedColumnBusiness["phone"] = $phone : NULL;
        $address1 ? $updatedColumnLocation["address1"] = $address1 : NULL;
        $address2 ? $updatedColumnLocation["address2"] = $address2 : NULL;
        $address3 ? $updatedColumnLocation["address3"] = $address3 : NULL;
        $latitude ? $updatedColumnLocation["latitude"] = $latitude : NULL;
        $longitude ? $updatedColumnLocation["longitude"] = $longitude : NULL;
        $city ? $updatedColumnLocation["city"] = $city : NULL;
        $state ? $updatedColumnLocation["state"] = $state : NULL;
        $country ? $updatedColumnLocation["country"] = $country : NULL;
        $zip_code ? $updatedColumnLocation["zip_code"] = $zip_code : NULL;

        $update = DB::transaction(function () use (
            $id,
            $updatedColumnBusiness,
            $images,
            $updatedColumnLocation,
            $categories
        ) {

            $updateBusiness = Business::where("id", $id)->update($updatedColumnBusiness);
            if (!$updateBusiness) {
                return Formatter::response(400, "Error", NULL, "Error update business.");
            }

            $updatedImages = [];
            if ($images) {
                $deleteImages = Image::where("business_id", $id)->delete();
                if (!$deleteImages) {
                    return Formatter::response(400, "Error", NULL, "Error deleting images.");
                }

                foreach ($images as $image) {
                    $storeImage = Storage::storeImage($image);
                    $insertImage = Image::create(["business_id" => $id, "url" => Storage::getImageUrl($storeImage)]);
                    array_push($updatedImages, $insertImage);
                }
            }

            $updatedCategories = [];
            if ($categories) {
                $deleteCategories = Category::where("business_id", $id)->delete();
                if (!$deleteCategories) {
                    return Formatter::response(400, "Error", NULL, "Error deleting categories.");
                }

                foreach ($categories as $category) {
                    $insertCategory = Category::create(["business_id" => $id, "title" => $category, "alias" => strtolower(trim(preg_replace("/[^A-Za-z0-9-]+/", "-", $category)))]);
                    array_push($updatedCategories, $insertCategory);
                }
            }

            $updateLocation = Location::where("business_id", $id)->update($updatedColumnLocation);
            if (!$updateLocation) {
                return Formatter::response(400, "Error", NULL, "Error update location.");
            }

            $updatedData = $updatedColumnBusiness;
            count($updatedImages) > 0 ? $updatedData["images"] = $updatedImages : NULL;
            $updatedData["location"] = $updatedColumnLocation;
            count($updatedCategories) > 0 ? $updatedData["categories"] = $updatedCategories : NULL;

            return $updatedData;
        });

        return Formatter::response(200, "Success update data.", ["data" => $update]);
    }

    public function delete($id)
    {
        $delete = DB::transaction(function () use ($id) {
            $deleteCategories = Category::where("business_id", $id)->delete();
            $deleteLocation = Location::where("business_id", $id)->delete();
            $deleteImages = Image::where("business_id", $id)->delete();
            $deleteBusiness = Business::where("id", $id)->delete();

            return true;
        });

        if (!$delete) {
            return Formatter::response(500, "Error", NULL, "Error delete data.");
        }

        return Formatter::response(200, "Success delete data.");
    }
}
