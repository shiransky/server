<?php
/**
 * @package plugins.cuePoints
 * @subpackage Scheduler
 */
abstract class KCopyCuePointEngine
{
    const MAX_CUE_POINT_CHUNKS = 500;
    
    protected $data = null;
    protected $partnerId = null;

    abstract public function copyCuePoints();

    abstract public function shouldCopyCuePoint($cuePoint);

    abstract public function calculateCuePointTimes($cuePoint);

    protected function validateJobData() {return true;}

    protected static function postProcessCuePoints($copiedCuePointIds) {}

    protected function copyCuePointsToEntry($srcEntryId, $destEntryId)
    {
        $filter = $this->getCuePointFilter($srcEntryId);
        $pager = $this->getCuePointPager();
        $clonedCuePointIds = array();
        do 
        {
            KalturaLog::debug("Getting list of cue point for entry [$srcEntryId] with pager index: " . $pager->pageIndex);
            $listResponse = KBatchBase::tryExecuteApiCall(array('KCopyCuePointEngine','cuePointList'), array($filter, $pager));
            if (!$listResponse)
                return false;
            foreach ($listResponse->objects as $cuePoint)
            {
                if ($this->shouldCopyCuePoint($cuePoint))
                {
                    $clonedCuePointId = $this->copySingleCuePoint($cuePoint, $destEntryId);
                    if ($clonedCuePointId)
                        $clonedCuePointIds[] = $clonedCuePointId;
                }
            }
            $pager->pageIndex++;
            
        } while (count($listResponse->objects) == self::MAX_CUE_POINT_CHUNKS);
        $this->postProcessCuePoints($clonedCuePointIds);
        return true;
    }

    protected function copySingleCuePoint($cuePoint, $destEntryId)
    {
        $clonedCuePoint = KBatchBase::tryExecuteApiCall(array('KCopyCuePointEngine','cuePointClone'), array($cuePoint->id, $destEntryId));
        if ($clonedCuePoint)
        {
            list($startTime, $endTime) = $this->calculateCuePointTimes($cuePoint);
            $res = KBatchBase::tryExecuteApiCall(array('KCopyCuePointEngine','updateCuePointTimes'), array($clonedCuePoint->id, $startTime, $endTime));
            if ($res)
                return $cuePoint->id;
            else
                KalturaLog::info("Update time for [{$cuePoint->id}] of [$startTime, $endTime] - Failed");
        } else
            KalturaLog::info("Could not copy [{$cuePoint->id}] - moving to next");
        return null;
    }


    public function setData($data, $partnerId) 
    {
        $this->data = $data;
        $this->partnerId = $partnerId;
    }
    
    public static function initEngine($copyCuePointJobType, $data, $partnerId)
    {
        $engine = self::getEngine($copyCuePointJobType);
        if (!$engine)
            return null;
        $engine->setData($data, $partnerId);
        return $engine;
    }

    private static function getEngine($copyCuePointJobType) {
        switch($copyCuePointJobType)
        {
            case CopyCuePointJobType::MULTI_SOURCES:
               return new KMultiSourceCopyCuePointEngine();
            case CopyCuePointJobType::LIVE:
                return new KLiveToVodCopyCuePointEngine();
            case CopyCuePointJobType::LIVE_CLIPPING:
                return new KLiveClippingCopyCuePointEngine();
            default:
                return null;
        }
    }

    protected function getCuePointFilter($entryId, $status = CuePointStatus::READY)
    {
        $filter = new KalturaCuePointFilter();
        $filter->entryIdEqual = $entryId;
        $filter->statusIn = $status;
        $filter->orderBy = '+createdAt';
        return $filter;
    }

    protected function getCuePointPager()
    {
        $pager = new KalturaFilterPager();
        $pager->pageIndex = 0;
        $pager->pageSize = self::MAX_CUE_POINT_CHUNKS;
        return $pager;
    }

    public static function updateCuePointTimes($cuePointId, $startTime, $endTime = null)
    {
        return KBatchBase::$kClient->cuePoint->updateCuePointsTimes($cuePointId, $startTime,$endTime);
    }

    public function cuePointList($filter, $pager)
    {
        return KBatchBase::$kClient->cuePoint->listAction($filter, $pager);
    }

    public function cuePointClone($cuePointId, $destinationEntryId)
    {
        return KBatchBase::$kClient->cuePoint->cloneAction($cuePointId, $destinationEntryId);
    }

    public function cuePointUpdateStatus($cuePointId, $newStatus)
    {
        return KBatchBase::$kClient->cuePoint->updateStatus($cuePointId, $newStatus);
    }
}
