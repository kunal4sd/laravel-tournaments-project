<?php

namespace Xoco70\KendoTournaments\TreeGen;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Xoco70\KendoTournaments\Models\Championship;
use Xoco70\KendoTournaments\Models\DirectEliminationFight;
use Xoco70\KendoTournaments\Models\Fight;
use Xoco70\KendoTournaments\Models\PreliminaryFight;

abstract class PlayOffTreeGen extends TreeGen
{


    /**
     * Calculate the Byes need to fill the Championship Tree.
     * @param Championship $championship
     * @param $fighters
     * @return Collection
     */
    protected function getByeGroup($fighters)
    {
        $fighterCount = $fighters->count();
        $preliminaryGroupSize = $this->championship->getSettings()->preliminaryGroupSize;
        $treeSize = $this->getTreeSize($fighterCount, $preliminaryGroupSize);
        $byeCount = $treeSize - $fighterCount;

        return $this->createByeGroup($byeCount);
    }

    /**
     * Save Groups with their parent info
     * @param integer $numRounds
     * @param $numFightersElim
     */
    protected function pushGroups($numRounds, $numFightersElim)
    {
        // TODO Here is where you should change when enable several winners for preliminary
        for ($roundNumber = 2; $roundNumber <= $numRounds + 1; $roundNumber++) {
            // From last match to first match
            $maxMatches = ($numFightersElim / pow(2, $roundNumber));

            for ($matchNumber = 1; $matchNumber <= $maxMatches; $matchNumber++) {
                $fighters = $this->createByeGroup(2);
                $group = $this->saveGroup($matchNumber, $roundNumber, null);
                $this->syncGroup($group, $fighters);
            }
        }
    }

    /**
     * Create empty groups for direct Elimination Tree
     * @param $numFighters
     */
    protected function pushEmptyGroupsToTree($numFighters)
    {
        $numFightersElim = $numFighters / $this->championship->getSettings()->preliminaryGroupSize * 2;
        // We calculate how much rounds we will have
        $numRounds = intval(log($numFightersElim, 2)); // 3 rounds, but begining from round 2 ( ie => 4)
        $this->pushGroups($numRounds, $numFightersElim);
    }

    /**
     * Chunk Fighters into groups for fighting, and optionnaly shuffle
     * @param $round
     * @param $fightersByEntity
     * @return mixed
     */
    protected function chunkAndShuffle($round, Collection $fightersByEntity)
    {
        if ($this->championship->hasPreliminary()) {
            $fightersGroup = $fightersByEntity->chunk($this->settings->preliminaryGroupSize);
            if (!App::runningUnitTests()) {
                $fightersGroup = $fightersGroup->shuffle();
            }
        } else { // Round Robin
            $fightersGroup = $fightersByEntity->chunk($fightersByEntity->count());
        }
        return $fightersGroup;
    }

    /**
     * Generate First Round Fights
     */
    protected function generateFights()
    {
        //  First Round Fights
        $settings = $this->championship->getSettings();
        parent::destroyPreviousFights();
        $groups = $this->championship->groupsByRound(1)->get();
        // Very specific case to common case : Preliminary with 3 fighters
        if ($settings->preliminaryGroupSize == 3) {
            for ($numFight = 1; $numFight <= $settings->preliminaryGroupSize; $numFight++) {
                $fight = new PreliminaryFight;
                $fight->saveFights($groups, $numFight);
            }
        }
        // Save Next rounds
        $fight = new DirectEliminationFight;
        $fight->saveFights($this->championship, 2);
    }


    /**
     * Return number of rounds for the tree based on fighter count
     * @param $numFighters
     * @return int
     */
    protected function getNumRounds($numFighters)
    {
        return intval(log($numFighters / $this->championship->getSettings()->preliminaryGroupSize * 2, 2));
    }
}
