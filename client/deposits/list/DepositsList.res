open Belt

// External libraries.
open Externals.WooCommerce.Components
open Externals.WooCommerce.Date
open Externals.WooCommerce.I18n
open Externals.WooCommerce.Navigation
open Externals.WooCommerce.Currency
open Externals.Moment

// Internal libraries.
open Externals.WCPay.Components
open Externals.WCPay.Util

type tableColumnKeys = [#details | #date | #disputeType | #amount | #status | #bankAccount]

let headers: array<TableCard.column<tableColumnKeys>> = {
  [
    TableCard.make_column(
      ~key=#details,
      ~label="",
      ~required=true,
      ~cellClassName="info-button",
      (),
    ),
    TableCard.make_column(
      ~key=#date,
      ~label=__("Date", "woocommerce-payments"),
      ~required=true,
      ~isLeftAligned=true,
      ~defaultOrder="desc",
      ~cellClassName="date-time",
      (),
    ),
    TableCard.make_column(
      ~key=#disputeType,
      ~label=__("Type", "woocommerce-payments"),
      ~required=true,
      (),
    ),
    TableCard.make_column(
      ~key=#amount,
      ~label=__("Amount", "woocommerce-payments"),
      ~required=true,
      ~isNumeric=true,
      (),
    ),
    TableCard.make_column(
      ~key=#status,
      ~label=__("Status", "woocommerce-payments"),
      ~required=true,
      (),
    ),
    TableCard.make_column(~key=#bankAccount, ~label=__("Bank account", "woocommerce-payments"), ()),
  ]
}

let currency = createCurrency()

@react.component
let default = () => {
  // Data layer
  open Data.Deposits

  let {deposits, isLoading} = Hooks.useDeposits(getQuery())

  let rows = deposits->Array.map(deposit => {
    let clickable = children =>
      <ClickableCell href={DetailsLink.getDetailsURL(deposit.id, "deposits")}>
        {children}
      </ClickableCell>
    let detailsLink = <DetailsLink id={deposit.id} parentSegment={"deposits"} />
    let dateDisplay =
      <Link href={DetailsLink.getDetailsURL(deposit.id, "deposits")}>
        {dateI18n("M j, Y", moment->utc(deposit.date)->toISOString, true)->React.string}
      </Link>

    // Sort of a direct conversion from the original JS code, but this is also useful since the
    // type system will give us a warning here if we ever add or remove columns!
    headers->Array.map(header => {
      open TableCard
      switch header.key {
      | #details => make_cell(deposit.id, detailsLink)
      | #date => make_cell(deposit.date, dateDisplay)
      | #disputeType =>
        make_cell(
          deposit.type_,
          clickable(
            Strings.displayType
            ->Js.Dict.get(deposit.type_)
            ->Option.getWithDefault("Estimated")
            ->React.string,
          ),
        )
      | #amount =>
        make_cell(
          deposit.amount /. 100.,
          clickable(currency->formatAmount(deposit.amount /. 100.)->React.string),
        )
      | #status =>
        make_cell(
          deposit.status,
          clickable(
            Strings.displayStatus
            ->Js.Dict.get(deposit.status)
            ->Option.getWithDefault(deposit.status->formatStringValue)
            ->React.string,
          ),
        )
      | #bankAccount => make_cell(deposit.bankAccount, clickable(deposit.bankAccount->React.string))
      }
    })
  })

  <TableCard
    title={__("Deposit history", "woocommerce-payments")}
    isLoading
    rowsPerPage=10.
    totalRows=10.
    headers
    rows
    query={getQuery()}
    onQueryChange
  />
}
