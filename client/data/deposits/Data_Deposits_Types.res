type deposit = {
  id: string,
  date: float,
  @bs.as("type") type_: string,
  amount: float,
  status: string,
  bankAccount: string,
}

type depositsData = {deposits: array<deposit>, isLoading: bool}
