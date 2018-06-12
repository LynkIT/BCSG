{if $cards}
	<table class="cardnumbers" cellpadding="0" cellspacing="0" border="0" width="100%">
		<tr>
			<th>Bank</th>
			<th>Card Number</th>
			<th>Expiry</th>
		</tr>
		{foreach $cards as $card}
		<tr>
			<td>{$card.bank}</td>
			<td>{$card.cardhash}</td>
			<td>{$card.expiryshort}</td>
		</tr>
		{/foreach}
	</table>
{else}
	<h2 class="center">No cards.</h2>
{/if}