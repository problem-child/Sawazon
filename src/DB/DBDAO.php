<?php

namespace DB;

use Model\Product;
use Sawazon\DAO\DAO;

class DBDAO implements DAO
{

    public function getMostViewedProducts($n = 5)
    {
        return (new Product())->loadAll("ORDER BY view_count DESC LIMIT $n");
    }

    public function getPricesFor($product_id, $numOfPrices = null)
    {
        $sql = "SELECT price, date_changed FROM ProductPrice WHERE product_id = ?"
            . " ORDER BY date_changed DESC";
        if ($numOfPrices != null)
            $sql .= " LIMIT $numOfPrices";

        $statement = DB::getPDO()->prepare($sql);
        $statement->execute([$product_id]);

        return $statement->fetchAll();
    }

    public function addPriceFor($product_id, $price)
    {
        $sql = "INSERT INTO ProductPrice (product_id, price) VALUES (?,?)";
        DB::getPDO()->prepare($sql)->execute([$product_id, $price]);
    }

    public function getProductNamesAndPrices($category_id, $numOfProducts, $expensive)
    {

        $last_date_sql = "SELECT date_changed FROM ProductPrice WHERE product_id = P . product_id"
            . " ORDER BY date_changed DESC LIMIT 1";

        $sql = "SELECT P . name AS name, PP . price AS price FROM Product AS P "
            . "JOIN ProductPrice AS PP ON P . product_id = PP . product_id "
            . "WHERE P . category_id = ? AND PP . date_changed = ($last_date_sql)"
            . "ORDER by price " . ($expensive ? "DESC" : "ASC")
            . " LIMIT $numOfProducts";

        $statement = DB::getPDO()->prepare($sql);
        $statement->execute([$category_id]);

        return $statement->fetchAll();
    }

    public function getRecentContentForUser($user_id, $post_limit, $product_limit)
    {
        // get all posts from from users i follow and me
        $posts = "SELECT DISTINCT 'post' AS type, post_id AS id, published_on AS date FROM Post "
            . "JOIN Follower ON (followee = user_id AND follower = $user_id OR user_id = $user_id) "
            . "LIMIT $post_limit";

        // get all products from users i follow and me
        $products = "SELECT DISTINCT 'product' AS type, product_id AS id, published_on AS date FROM Product "
            . "JOIN Follower ON (followee = user_id AND follower = $user_id OR user_id = $user_id) "
            . "LIMIT $product_limit";

        $sql = "SELECT type, id FROM($posts UNION ALL $products) T ORDER BY date DESC";

        $statement = DB::getPDO()->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getTaggedWith($tags)
    {
        if (empty($tags)) return [];

        $sql = "SELECT DISTINCT content_id AS id, content_type AS type FROM Tag ";
        if (is_array($tags)) {
            $q = [];
            foreach ($tags as $t) $q[] = '?';
            $sql .= "WHERE tag IN (" . implode(',', $q) . ")";
        } else {
            $sql .= "WHERE tag = ?";
            $tags = [$tags];
        }

        $statement = DB::getPDO()->prepare($sql);
        $statement->execute($tags);
        return $statement->fetchAll();
    }

    public function updateTags($id, $type, $tags)
    {
        // delete old ones
        DB::getPDO()->prepare(
            "DELETE FROM Tag WHERE content_id = ? AND content_type = ?"
        )->execute([$id, $type]);

        if (empty($tags)) return;

        $sql = "INSERT INTO Tag (content_id, content_type, tag) VALUES ";

        $sql .= implode(
            ", ",
            array_map(
                function ($tag) use ($id, $type) {
                    return "($id, '$type', ?)";
                },
                $tags
            )
        );

        $sql .= " ON DUPLICATE KEY UPDATE tag = tag"; // if someone writes #a #b #a

        DB::getPDO()->prepare($sql)->execute($tags);
    }

    public function checkFollows($follower, $followee)
    {
        $sql = "SELECT 1 FROM Follower WHERE follower=? AND followee=?";
        $statement = DB::getPDO()->prepare($sql);
        $statement->execute([$follower, $followee]);
        return $statement->rowCount() != 0;
    }

    public function modifyFollow($follower, $followee, $action)
    {
        if ($action == 'delete')
            $sql = "DELETE FROM Follower WHERE follower=? AND followee=?";
        else
            $sql = "INSERT IGNORE INTO Follower (follower, followee) VALUES (?, ?) "
                . "ON DUPLICATE KEY UPDATE followee = followee";

        DB::getPDO()->prepare($sql)->execute([$follower, $followee]);
    }

}