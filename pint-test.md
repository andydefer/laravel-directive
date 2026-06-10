# Pint Formatting Test Report
*Generated: mer. 10 juin 2026 19:03:21 WAT*


  ⨯...............................................⨯.⨯...⨯⨯......⨯.⨯⨯...⨯...⨯...⨯....................⨯...⨯.......⨯...⨯.⨯⨯...⨯..⨯⨯⨯.⨯...............⨯.⨯..⨯⨯⨯.⨯⨯....⨯⨯⨯⨯................⨯
  ....⨯⨯.⨯..⨯⨯.⨯...⨯⨯...⨯⨯.....⨯......

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................ 216 files, 45 style issues  
  ⨯ src/AbstractDirective.php                                                                                               braces_position, no_unused_imports, single_line_empty_body  
  ⨯ src/Cli/CliRunner.php                                                                        class_attributes_separation, new_with_parentheses, function_declaration, concat_space  
  ⨯ src/Configs/EnvDirectiveConfig.php                                                                                                                                    concat_space  
  ⨯ src/Contexts/DirectiveContext.php                                                                                         class_attributes_separation, blank_line_before_statement  
  ⨯ src/Contexts/DirectiveDiscoveryContext.php                                                                                               class_attributes_separation, concat_space  
  ⨯ src/Contexts/DirectiveTestingContext.php                                                                                                 class_attributes_separation, concat_space  
  ⨯ src/Contexts/LaravelBootstrapperContext.php class_attributes_separation, concat_space, no_trailing_whitespace_in_comment, unary_operator_spaces, not_operator_with_successor_spac…  
  ⨯ src/Contracts/Configs/DirectiveConfigInterface.php                                                                                               no_trailing_whitespace_in_comment  
  ⨯ src/Contracts/DirectiveTestingServiceInterface.php                                                        no_superfluous_phpdoc_tags, phpdoc_separation, phpdoc_trim, phpdoc_align  
  ⨯ src/DirectiveServiceProvider.php                                                                                       fully_qualified_strict_types, concat_space, ordered_imports  
  ⨯ src/Services/ArgumentSplitterService.php                                                                      braces_position, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveDiscoveryService.php                                                            concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Services/DirectiveExecutionService.php                                                              braces_position, not_operator_with_successor_space, single_line_empty_body  
  ⨯ src/Services/DirectiveHydratorService.php                                                                                            fully_qualified_strict_types, ordered_imports  
  ⨯ src/Services/DirectiveNamingService.php                      concat_space, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveParserService.php                                                                                                                          no_unused_imports  
  ⨯ src/Services/DirectiveTestingService.php class_attributes_separation, function_declaration, single_quote, fully_qualified_strict_types, concat_space, no_unused_imports, not_oper…  
  ⨯ src/Services/FileCreatorService.php                                                                         concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ src/Steps/BootstrapLaravelStep.php                                                    concat_space, unary_operator_spaces, not_operator_with_successor_space, no_extra_blank_lines  
  ⨯ src/Steps/BuildContainerStep.php     function_declaration, fully_qualified_strict_types, concat_space, unary_operator_spaces, no_unused_imports, not_operator_with_successor_space  
  ⨯ src/Steps/StartDatabaseStep.php          single_quote, fully_qualified_strict_types, concat_space, not_operator_with_successor_space, blank_line_before_statement, ordered_imports  
  ⨯ src/Testing/InteractsWithDirectives.php class_attributes_separation, concat_space, no_trailing_whitespace_in_comment, unary_operator_spaces, phpdoc_separation, not_operator_with…  
  ⨯ stubs/laravel/bootstrap/app.php                                                                                                                               no_extra_blank_lines  
  ⨯ stubs/laravel/config/app.php                                                                fully_qualified_strict_types, concat_space, single_line_after_imports, ordered_imports  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                                                                            concat_space  
  ⨯ tests/Feature/LaravelDatabaseDirectiveTest.php                                                                                                                        concat_space  
  ⨯ tests/Feature/LaravelDatabaseIntegrationTest.php                                                                                                                      concat_space  
  ⨯ tests/Fixtures/Directives/TestCalculatorDirective.php                                                                               class_attributes_separation, no_unused_imports  
  ⨯ tests/Fixtures/Directives/TestConcreteDirective.php                                                                                                              no_unused_imports  
  ⨯ tests/Fixtures/Directives/TestEchoDirective.php                                                                                                                  no_unused_imports  
  ⨯ tests/Fixtures/Directives/TestLaravelDatabaseDirective.php                                                  concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/IntegrationTestCase.php                                                                               concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/Unit/AbstractDirectiveTest.php                                                                                                                   class_attributes_separation  
  ⨯ tests/Unit/Cli/CliRunnerTest.php                                  class_attributes_separation, new_with_parentheses, concat_space, not_operator_with_successor_space, phpdoc_align  
  ⨯ tests/Unit/DirectiveServiceProviderTest.php                                                      class_attributes_separation, class_definition, braces_position, no_unused_imports  
  ⨯ tests/Unit/Services/DirectiveDiscoveryServiceTest.php                                                                                    class_attributes_separation, concat_space  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                                                                                                                 concat_space  
  ⨯ tests/Unit/Services/DirectiveHydratorServiceTest.php                                                class_attributes_separation, no_extra_blank_lines, blank_line_before_statement  
  ⨯ tests/Unit/Services/DirectiveTestingServiceDatabaseTest.php                                                                                                           concat_space  
  ⨯ tests/Unit/Services/DirectiveTestingServiceTest.php                                                         class_attributes_separation, concat_space, blank_line_before_statement  
  ⨯ tests/Unit/Services/SignatureValidationServiceTest.php                                                                                                                concat_space  
  ⨯ tests/Unit/Testing/ClosureDirectiveTest.php                                                         class_attributes_separation, function_declaration, blank_line_before_statement  
  ⨯ tests/Unit/Testing/InteractsWithDirectivesTest.php                                                                                       concat_space, blank_line_before_statement  
  ⨯ tests/Unit/Testing/TestDirectiveRegistryTest.php                                                                                                       class_attributes_separation  
  ⨯ tests/bootstrap/app.php                                                                                     concat_space, unary_operator_spaces, not_operator_with_successor_space  

