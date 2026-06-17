from app.providers.fake import FakeProvider


def test_investment_intent_extracts_amount_and_months() -> None:
    intent = FakeProvider().parse_intent("how much does 10 thousand in savings yield over 2 years?")
    assert intent["type"] == "investment_return"
    assert intent["params"]["principal"] == 10_000
    assert intent["params"]["months"] == 24
    assert intent["params"]["indicator"] == "poupanca"


def test_cdb_intent_extracts_percent_of_cdi() -> None:
    intent = FakeProvider().parse_intent(
        "how much does a CDB at 110% of CDI on 5000 yield over 2 years?"
    )
    assert intent["type"] == "investment_return"
    assert intent["params"]["indicator"] == "cdi"
    assert intent["params"]["percent_of_cdi"] == 110.0
    assert intent["params"]["principal"] == 5000
    assert intent["params"]["months"] == 24


def test_inflation_intent() -> None:
    intent = FakeProvider().parse_intent("how much is 1000 worth today adjusted for inflation?")
    assert intent["type"] == "inflation_correction"
    assert intent["params"]["amount"] == 1000


def test_indicator_value_intent() -> None:
    intent = FakeProvider().parse_intent("what is the current cdi?")
    assert intent["type"] == "indicator_value"
    assert intent["params"]["indicator"] == "cdi"


def test_explain_investment() -> None:
    answer = FakeProvider().explain(
        {"type": "investment_return"},
        {
            "type": "investment_return",
            "principal": 1000.0,
            "months": 12,
            "indicator": "poupanca",
            "monthly_rate_pct": 0.5,
            "result": 1061.68,
            "earnings": 61.68,
        },
    )
    assert "1061.68" in answer
