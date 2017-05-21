<?php

namespace Xoco70\KendoTournaments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Fight extends Model
{
    /**
     * Fight constructor.
     * @param int $userId1
     * @param int $userId2
     */
    public function __construct($userId1 = null, $userId2 = null)
    {
        $this->c1 = $userId1;
        $this->c2 = $userId2;
    }

    protected $table = 'fight';
    public $timestamps = true;

    protected $fillable = [
        'group_id',
        'c1',
        'c2',
    ];

    /**
     * Get First Fighter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(FightersGroup::class, 'fighters_group_id');
    }

    /**
     * @param Championship $championship
     *
     * @return Collection
     */
    private static function getActorsToFights(Championship $championship, FightersGroup $group = null)
    {
        if ($championship->category->isTeam) {
            $fighters = $group->teams()->get();
            if (sizeof($fighters) == 0) {
                $fighters->push(new Team());
                $fighters->push(new Team());
            } else if (count($fighters) % 2 != 0) {
                $fighters->push(new Team(['name' => 'BYE']));
            }

        } else {
            $fighters = $group->competitors()->get();
            if (sizeof($fighters) == 0) { // If
                $fighters->push(new Competitor());
                $fighters->push(new Competitor());
            } else if (count($fighters) % 2 != 0) { // If fighter is not pair, add a BYE
                $fighters->push(new Competitor());
            }


        }

        return $fighters;
    }

    /**
     * Get First Fighter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function competitor1()
    {
        return $this->belongsTo(Competitor::class, 'c1', 'id');
    }

    /**
     * Get Second Fighter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function competitor2()
    {
        return $this->belongsTo(Competitor::class, 'c2', 'id');
    }

    /**
     * Get First Fighter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team1()
    {
        return $this->belongsTo(Team::class, 'c1', 'id');
    }

    /**
     * Get Second Fighter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team2()
    {
        return $this->belongsTo(Team::class, 'c2', 'id');
    }

    /**
     * Save a Fight.
     *
     * @param Collection $groups
     * @param int $numGroup
     */
    public static function savePreliminaryFightGroup($groups, $numGroup = 1)
    {
        $competitor1 = $competitor2 = null;
        $order = 1;

        foreach ($groups as $group) {

            if ($group->championship->category->isTeam()) {
                $fighters = $group->teams;
            } else {
                $fighters = $group->competitors;
            }

            $fighter1 = $fighters->get(0);
            $fighter2 = $fighters->get(1);
            $fighter3 = $fighters->get(2);

            switch ($numGroup) {
                case 1:
                    $competitor1 = $fighter1;
                    $competitor2 = $fighter2;
                    break;
                case 2:
                    $competitor1 = $fighter2;
                    $competitor2 = $fighter3;
                    break;
                case 3:
                    $competitor1 = $fighter3;
                    $competitor2 = $fighter1;
                    break;
            }
            $fight = new self();
            $fight->fighters_group_id = $group->id;
            $fight->c1 = $competitor1 != null ? $competitor1->id : null;
            $fight->c2 = $competitor2 != null ? $competitor2->id : null;
            $fight->short_id = $order++;
            $fight->area = $group->area;
            $fight->save();
        }
    }


    /**
     * @param Championship $championship
     */
    public static function saveGroupFights(Championship $championship)
    {
        $order = 1;
        foreach ($championship->fightersGroups()->get() as $group) {
            $fighters = self::getActorsToFights($championship, $group);
            $away = $fighters->splice(count($fighters) / 2); // 2
            $home = $fighters; // 1

            for ($i = 0; $i < count($home) + count($away) - 1; $i++) { // 0 -> 2
                for ($j = 0; $j < count($home); $j++) {  // 1 no mas

                    $round[$i][$j]['Home'] = $home[$j];
                    $round[$i][$j]['Away'] = $away[$j];
                    $fight = new self();
                    $fight->fighters_group_id = $group->id;
                    $fight->c1 = $round[$i][$j]['Home']->id;
                    $fight->c2 = $round[$i][$j]['Away']->id;
                    $fight->short_id = $order++;
                    $fight->area = $group->area;
                    $fight->save();

                }
                if (count($home) + count($away) - 1 > 2) {
                    $away->prepend($home->splice(1, 1)->shift());
                    $home->push($away->pop());
                    $order++;
                }
            }
        }
    }

    public function getFighterAttr($numFighter, $attr)
    {
        $isTeam = $this->group->championship->category->isTeam;
        if ($isTeam) {
            $teamToUpdate = 'team' . $numFighter;
            return $this->$teamToUpdate == null ? '' : $this->$teamToUpdate->$attr;
        }
        $competitorToUpdate = 'competitor' . $numFighter;
        if ($attr == 'name') {
            return $this->$competitorToUpdate == null
                ? 'BYE'
                : $this->$competitorToUpdate->user->firstname . " " . $this->$competitorToUpdate->user->lastname;
        } elseif ($attr == 'short_id') {
            return $this->$competitorToUpdate == null ? '' : $this->$competitorToUpdate->short_id;
        }
        return null;
    }

//    public function getFighterShortId($numFighter,$attr)
//    {
//        $isTeam = $this->group->championship->category->isTeam;
//        if ($isTeam) {
//            $teamToUpdate = 'team' . $numFighter;
//            return $this->$teamToUpdate == null ? '' : $this->$teamToUpdate->$attr;
//        }
//        $competitorToUpdate = 'competitor' . $numFighter;
//        return $this->$competitorToUpdate == null ? '' : $this->$competitorToUpdate->short_id;
//    }

    /**
     * Update parent Fight
     */
    public function updateParentFight($fighterToUpdate, $fight)
    {

        if ($fight != null && ($fight->c1 != null || $fight->c2 == null)) {
            $this->$fighterToUpdate = $fight->c1;
        }
        if ($fight != null && $fight->c1 == null || $fight->c2 != null) {
            $this->$fighterToUpdate = $fight->c2;
        }
        if ($fight->c1 == null || $fight->c2 == null) {
            $this->$fighterToUpdate = null;
        }
    }

    /**
     * Returns the parent field that need to be updated
     * @return null|string
     */
    public function getParentFighterToUpdate()
    {
        $childrenGroup = $this->group->parent->children;
        foreach ($childrenGroup as $key => $children) {
            $childFight = $children->fights->get(0);
            if ($childFight->id == $this->id) {
                if ($key % 2 == 0) {
                    return "c1";
                }
                if ($key % 2 == 1) {
                    return "c2";
                }
            }
        }
        return null;
    }

    /**
     * In the original fight ( child ) return the field that contains data to copy to parent
     * @return null|string
     */
    public function getValueToUpdate()
    {
        if ($this->c1 != null && $this->c2 != null) {
            return null;
        }
        if ($this->c1 != null) {
            return "c1";
        }
        if ($this->c2 != null) {
            return "c2";
        }
        return null;
    }

}