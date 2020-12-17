open Belt

/**
 * External components.
 */

type query
type function

@bs.module("@woocommerce/navigation")
external getQuery: unit => query = "getQuery"
@bs.module("@woocommerce/navigation")
external onQueryChange: function = "onQueryChange"

module TableCard = {
  type cell;
  type row = array<cell>

  type column<'columnKey> = {
    key: 'columnKey,
    label: string,
    isNumeric: option<bool>,
    isLeftAligned: option<bool>,
    defaultOrder: option<string>,
    cellClassName: option<string>,
    required: option<bool>,
  }

  @bs.module("@woocommerce/components") @react.component
  external make: (
    ~title: string,
    ~isLoading: bool,
    ~rowsPerPage: float,
    ~totalRows: float,
    ~headers: array<column<'columnKey>>,
    ~rows: array<row>,
    ~query: query,
    ~onQueryChange: function,
  ) => React.element = "TableCard"
  // external make: (~title, ~isLoading, ~rowsPerPage, ~totalRows, ~headers, ~rows, ~query, ~onQueryChange) => React.element = "default";

  let make_column = (
    ~key,
    ~label,
    ~required=?,
    ~isLeftAligned=?,
    ~isNumeric=?,
    ~defaultOrder=?,
    ~cellClassName=?,
    (),
  ) => {
    key: key,
    label: label,
    isLeftAligned: isLeftAligned,
    isNumeric: isNumeric,
    defaultOrder: defaultOrder,
    cellClassName: cellClassName,
    required: required,
  }

  // This is an unfortunate hack we need to use because the value in each cell can be either
  // a string, number, or bool, which we would normally model with a Variant in ReScript. However,
  // Variant types are wrapped in an object once compiled to JS, and as such not compatible with
  // how TableCard expects each cell to be structured.
  // So, we just embed raw JS here that accepts a value and display object and returns an object
  // that's correctly formatted for TableCard.
  let make_cell = %raw(`
  function(value, display) {
	  return { value: value, display: display }
  }
  `)
}
module Link = {
  @bs.module("@woocommerce/components") @react.component
  external make: (~href: string, ~children: React.element) => React.element = "Link"
}
module Moment = {
  type t

  @bs.module("moment")
  external moment: t = "default"

  @bs.send
  external utc: (t, float) => t = "utc"

  @bs.send
  external toISOString: t => string = "toISOString"
}
module Currency = {
  type t

  @bs.module("@woocommerce/currency")
  @bs.new external createCurrency: unit => t = "default"

  @bs.send external formatCurrency: (t, float) => string = "formatCurrency"
}

@bs.module("@wordpress/date")
external dateI18n: (string, string, bool) => string = "dateI18n"

@bs.module("@wordpress/i18n")
external __: (string, string) => string = "__"

/**
 * Internal components.
 */
module ClickableCell = {
  @bs.module("components/clickable-cell") @react.component
  external make: (~href: string, ~children: React.element) => React.element = "default"
}
module DetailsLink = {
  @bs.module("components/details-link") @react.component
  external make: (~id: string, ~parentSegment: string) => React.element = "default"
}

type deposit = {
  id: string,
  date: float,
  @bs.as("type") type_: string,
  amount: float,
  status: string,
  bankAccount: string,
}
type depositsData = {deposits: array<deposit>, isLoading: bool}

@bs.module("data")
external useDeposits: query => depositsData = "useDeposits"

@bs.module("components/details-link")
external getDetailsURL: (string, string) => string = "getDetailsURL"

@bs.module("utils")
external formatStringValue: string => string = "formatStringValue"

type tableColumnKeys = [#details | #date | #disputeType | #amount | #status | #bankAccount]

@bs.module("../strings")
@bs.val external displayType: 'a = "displayType"
@bs.module("../strings")
@bs.val external displayStatus: Js.Dict.t<string> = "displayStatus"

let headers: array<TableCard.column<tableColumnKeys>> = {
  open TableCard
  [
    make_column(~key=#details, ~label="", ~required=true, ~cellClassName="info-button", ()),
    make_column(
      ~key=#date,
      ~label=__("Date", "woocommerce-payments"),
      ~required=true,
      ~isLeftAligned=true,
      ~defaultOrder="desc",
      ~cellClassName="date-time",
      (),
    ),
    make_column(~key=#disputeType, ~label=__("Type", "woocommerce-payments"), ~required=true, ()),
    make_column(
      ~key=#amount,
      ~label=__("Amount", "woocommerce-payments"),
      ~required=true,
      ~isNumeric=true,
      (),
    ),
    make_column(~key=#status, ~label=__("Status", "woocommerce-payments"), ~required=true, ()),
    make_column(~key=#bankAccount, ~label=__("Bank account", "woocommerce-payments"), ()),
  ]
}

let currency = Currency.createCurrency()

@react.component
let default = () => {
  let {deposits, isLoading} = useDeposits(getQuery())

  let rows = deposits->Array.map(deposit => {
    let clickable = children =>
      <ClickableCell href={getDetailsURL(deposit.id, "deposits")}> {children} </ClickableCell>
    let detailsLink = <DetailsLink id={deposit.id} parentSegment={"deposits"} />
    let dateDisplay =
      <Link href={getDetailsURL(deposit.id, "deposits")}>
        {dateI18n(
          "M j, Y",
          Moment.utc(Moment.moment, deposit.date)->Moment.toISOString,
          true,
        )->React.string}
      </Link>

    headers->Array.map(header => {
      open TableCard
      switch header.key {
      | #details => make_cell(deposit.id, detailsLink)
      | #date => make_cell(deposit.date, dateDisplay)
      | #disputeType =>
        make_cell(deposit.type_, clickable(displayType["date"]))
      | #amount =>
        make_cell(
          deposit.amount /. 100.,
          clickable(
            currency->Currency.formatCurrency(deposit.amount /. 100.)->React.string,
          ),
        )
      | #status =>
        make_cell(
          deposit.status,
          clickable(
            displayStatus
            ->Js.Dict.get(deposit.status)
            ->Option.getWithDefault(deposit.status->formatStringValue)
            ->React.string,
          ),
        )
      | #bankAccount =>
        make_cell(
          deposit.bankAccount,
          clickable(deposit.bankAccount->React.string),
        )
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
