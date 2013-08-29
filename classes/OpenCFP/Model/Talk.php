<?php
/**
 * Object that represents a talk that a speaker has submitted
 */
namespace OpenCFP\Model;

class Talk
{
    protected $_db;

    /**
     * Constructor for the class
     *
     * @param PDO $db
     */
    public function __construct($db)
    {
        $this->_db = $db;
    }

    /**
     * Create a talk when you pass new data in
     *
     * @param array $data
     * @return boolean
     */
    public function create($data)
    {
        $sql = "
            INSERT INTO talks
            (title, description, type, level, category, desired, slides, other, sponsor, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
        $stmt = $this->_db->prepare($sql);

        return $stmt->execute(
            array(
                trim($data['title']),
                trim($data['description']),
                trim($data['type']),
                trim($data['level']),
                trim($data['category']),
                trim($data['desired']),
                trim($data['slides']),
                trim($data['other']),
                trim($data['sponsor']),
                $data['user_id']
            )
        );
    }

    /**
     * Return a record that matches an ID passed into it
     *
     * @param int $id
     * @return array|false
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM talks WHERE id = ?";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array($id));

        return $stmt->fetch() ?: false;
    }

    /**
     * Return one or more Talk records that match a user ID passed in
     *
     * @param integer $userId
     * @return false|array
     */
    public function findByUserId($userId)
    {
        $sql = "SELECT * FROM talks WHERE user_id = ? ORDER BY title";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array($userId));

        return $stmt->fetchAll();

    }

    /**
     * Update a record using data passed in to it
     *
     * @param array $data
     * @return boolean
     */
    public function update($data)
    {
        if (empty($data['id'])) {
            return false;
        }

        $sql = "UPDATE talks
            SET title = ?,
            description = ?,
            type = ?,
            level = ?,
            category = ?,
            desired = ?,
            slides = ?,
            other = ?,
            sponsor = ?
            WHERE id = ?
            AND user_id = ?
        ";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array(
            trim($data['title']),
            trim($data['description']),
            trim($data['type']),
            trim($data['level']),
            trim($data['category']),
            trim($data['desired']),
            trim($data['slides']),
            trim($data['other']),
            trim($data['sponsor']),
            $data['id'],
            $data['user_id']
        ));

        return ($stmt->rowCount() === 1);
    }

    /**
     * Delete a Talk record given a talk ID and a user ID
     *
     * @param integer $talkId
     * @param integer $userId
     * @return boolean
     */
    public function delete($talkId, $userId)
    {
        $sql = "DELETE FROM talks WHERE id = ? AND user_id = ?";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array($talkId, $userId));

        return ($stmt->rowCount() === 1);
    }

    /**
     * Return an array of all the talks, ordered by the title by default 
     * by default
     *
     * @param string $order default is 'title'
     * @return array
     */
    public function getAll($orderBy = 'title')
    {
        $sql = "SELECT * FROM talks ORDER BY {$orderBy}";
        $stmt = $this->_db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll();
        $talks = array();

        $stmt = $this->_db->prepare($sql);
        $stmt->execute();

        $sql = "SELECT email FROM users WHERE id = ?";
        $stmt = $this->_db->prepare($sql);

        foreach ($results as $result) {
            $stmt->execute(array($result['user_id']));
            $userDetails = $stmt->fetch();
            $talkInfo = $result;
            $talkInfo['user'] = $userDetails['email'];
            $talks[] = $talkInfo;
        }

        return $talks;
    }
}
