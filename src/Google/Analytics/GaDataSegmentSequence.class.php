<?php
namespace Google\Analytics;

class GaDataSegmentSequence extends GaDataSegmentGroup {	
	const PREFIX = 'sequence';
	
	/**
	 * Adds a step to this sequence.
	 *
	 * @param Google\Analytics\GaDataSegmentSequenceCondition $member
	 */
	protected function _addMember($member) {
		if (!is_object($member) ||
		    !($member instanceof GaDataSegmentSequenceCondition))
		{
			throw new InvalidArgumentException(
				'Segment members must be ' .
				__NAMESPACE__ . '\GaDataSegmentSequenceCondition instances.'
			);
		}
		$operator = $member->getConstraintAgainstPrevious();
		if (!$this->_members && $operator &&
		    $operator != GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP)
		{
			throw new InvalidArgumentException(
				'The first step of a sequence may not have a restriction ' .
				'against a previous condition other than a restriction that ' .
				'the first hit match the first step.'
			);
		}
		elseif ($this->_members) {
			if ($operator == GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP)
			{
				throw new InvalidArgumentException(
					'Only the first step of a sequence may have a ' .
					'restriction that the first hit matches the first step.'
				);
			}
			if (!$operator && $member->getLeftOperand() != 'dateOfSession')
			{
				throw new InvalidArgumentException(
					'Steps in a sequence beyond the first must have a ' .
					'constraint against the previous.'
				);
			}
		}
		$this->_members[] = $member;
	}
}
?>