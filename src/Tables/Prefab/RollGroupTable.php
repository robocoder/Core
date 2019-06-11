<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Tables\Prefab;

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;
use Gibbon\Forms\Input\Checkbox;
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\Students\StudentGateway;

/**
 * RollGroupTable
 *
 * @version v18
 * @since   v18
 */
class RollGroupTable extends DataTable
{
    protected $db;
    protected $session;
    protected $studentGateway;

    public function __construct(GridView $renderer, StudentGateway $studentGateway, Connection $db, Session $session)
    {
        parent::__construct($renderer);

        $this->db = $db;
        $this->session = $session;
        $this->studentGateway = $studentGateway;
    }

    public function build($gibbonRollGroupID, $canViewConfidential, $canPrint, $sortBy = 'rollOrder, surname, preferredName')
    {
        $guid = $this->session->get('guid');
        $connection2 = $this->db->getConnection();

        $highestAction = getHighestGroupedAction($guid, '/modules/Students/student_view_details.php', $connection2);
        
        $canViewStudents = ($highestAction == 'View Student Profile_brief' || $highestAction == 'View Student Profile_full' || $highestAction == 'View Student Profile_fullNoNotes');
        
        if ($canViewConfidential && !$canViewStudents) {
            $canViewConfidential = false;
        }

        if ($canPrint && isActionAccessible($guid, $connection2, '/modules/Students/report_students_byRollGroup_print.php') == false) {
            $canPrint = false;
        }

        $sortByArray = array_map('trim', explode(',', $sortBy));
        $criteria = $this->studentGateway
            ->newQueryCriteria()
            ->sortBy($sortByArray)
            ->pageSize(0);

        $students = $this->studentGateway->queryStudentEnrolmentByRollGroup($criteria, $gibbonRollGroupID);
        $this->withData($students);

        $this->setTitle(__('Students'));

        $this->addMetaData('gridClass', 'rounded-sm bg-blue-100 border');
        $this->addMetaData('gridItemClass', 'w-1/2 sm:w-1/3 md:w-1/5 mb-2 sm:mb-4 text-center');
        

        if ($canPrint) {
            $this->addHeaderAction('print', __('Print'))
                ->setURL('/report.php')
                ->addParam('q', '/modules/Students/report_students_byRollGroup_print.php')
                ->addParam('gibbonRollGroupID', $gibbonRollGroupID)
                ->addParam('view', 'Basic')
                ->setIcon('print')
                ->setTarget('_blank')
                ->directLink()
                ->displayLabel();
        }

        if ($canViewConfidential) {
            $checkbox = (new Checkbox('confidential'.$gibbonRollGroupID))
                ->description(__('Show Confidential Data'))
                ->checked(true)
                ->inline()
                ->wrap('<div class="my-2 text-right text-xxs text-gray-700 italic">', '</div>');

            $this->addMetaData('gridHeader', $checkbox->getOutput());
            $this->addMetaData('gridFooter', $this->getCheckboxScript($gibbonRollGroupID));

            $this->addColumn('alerts')
                ->format(function ($person) use ($guid, $connection2, $gibbonRollGroupID) {
                    $divExtras = ' data-conf="confidential'.$gibbonRollGroupID.'"';
                    return getAlertBar($guid, $connection2, $person['gibbonPersonID'], $person['privacy'], $divExtras);
                });
        }

        $this->addColumn('image_240')
            ->setClass('relative')
            ->format(function ($person) {
                return Format::userPhoto($person['image_240'], 'md', '').
                       Format::userBirthdayIcon($person['dob'], $person['preferredName']);
            });
            
        $this->addColumn('name')
            ->setClass('text-xs font-bold mt-1')
            ->format(function ($person) use ($canViewStudents) {
                $name = Format::name($person['title'], $person['preferredName'], $person['surname'], 'Student', false, true);
                $url =  './index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$person['gibbonPersonID'];

                return $canViewStudents
                    ? Format::link($url, $name)
                    : $name;
            });

        $this->addColumn('role')
            ->setClass('text-xs text-gray-600 italic leading-snug')
            ->translatable();
    }

    private function getCheckboxScript($id)
    {
        return '
        <script type="text/javascript">
        $(function () {
            $("#confidential'.$id.'").click(function () {
                $("[data-conf=\'confidential'.$id.'\']").slideToggle(!$(this).is(":checked"));
            });
        });
        </script>';
    }
}
